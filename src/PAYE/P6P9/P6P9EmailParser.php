<?php

namespace HMRC\PAYE\P6P9;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parse P6/P9 notices from HMRC emails
 * 
 * Integrates with email fetching (IMAP) to automatically detect
 * and parse P6/P9 tax code change notifications
 */
class P6P9EmailParser
{
    protected $monitor;
    protected $imapConnection;
    protected $logger;
    
    public function __construct(P6P9Monitor $monitor, ?LoggerInterface $logger = null)
    {
        $this->monitor = $monitor;
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Connect to email inbox
     * 
     * @param string $host IMAP server (e.g., imap.gmail.com)
     * @param string $username Email address
     * @param string $password Email password or app password
     * @param string $folder Email folder (default: INBOX)
     * @param bool $ssl Use SSL (default: true)
     * @return bool Connection successful
     */
    public function connect(
        string $host, 
        string $username, 
        string $password, 
        string $folder = 'INBOX',
        bool $ssl = true
    ): bool {
        try {
            $protocol = $ssl ? '/imap/ssl' : '/imap';
            $mailbox = "{{$host}{$protocol}}{$folder}";
            
            $this->imapConnection = @imap_open(
                $mailbox,
                $username,
                $password
            );
            
            if ($this->imapConnection === false) {
                $error = imap_last_error();
                $this->logger->error("Failed to connect to email: {$error}");
                return false;
            }
            
            $this->logger->info("Connected to email server: {$host}");
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to connect to email: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fetch and parse P6/P9 emails from today
     */
    public function fetchTodaysNotices(): array
    {
        return $this->fetchNoticesSince(date('d-M-Y'));
    }
    
    /**
     * Fetch and parse P6/P9 emails since a specific date
     * 
     * @param string $since Date in format 'd-M-Y' (e.g., '01-Jan-2025')
     * @return array Parsed notices
     */
    public function fetchNoticesSince(string $since): array
    {
        if (!$this->imapConnection) {
            throw new \Exception('Not connected to email server');
        }
        
        $notices = [];
        
        // Search for emails from HMRC with P6/P9 keywords
        $searchCriteria = "FROM noreply@tax.service.gov.uk SINCE {$since}";
        $emails = @imap_search($this->imapConnection, $searchCriteria);
        
        if (!$emails) {
            $this->logger->info("No P6/P9 emails found since {$since}");
            return $notices;
        }
        
        $this->logger->info("Found " . count($emails) . " potential P6/P9 emails");
        
        foreach ($emails as $emailId) {
            try {
                $header = imap_headerinfo($this->imapConnection, $emailId);
                $subject = $header->subject ?? '';
                
                // Check if it's a P6/P9 notice
                if (!$this->isP6P9Email($subject)) {
                    continue;
                }
                
                $body = $this->getEmailBody($emailId);
                $parsed = $this->monitor->parseP6P9Email($body);
                
                if ($parsed !== null) {
                    $this->monitor->storeNotice($parsed['nino'], $parsed);
                    $notices[] = $parsed;
                    
                    $this->logger->info("Parsed P6/P9 from email for {$parsed['nino']}: {$parsed['taxCode']}");
                    
                    // Mark email as read/flagged
                    @imap_setflag_full($this->imapConnection, (string)$emailId, "\\Seen \\Flagged");
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to process email {$emailId}: " . $e->getMessage());
                continue;
            }
        }
        
        return $notices;
    }
    
    /**
     * Fetch all unread P6/P9 emails
     */
    public function fetchUnreadNotices(): array
    {
        if (!$this->imapConnection) {
            throw new \Exception('Not connected to email server');
        }
        
        $notices = [];
        
        // Search for unread emails from HMRC
        $emails = @imap_search($this->imapConnection, 'UNSEEN FROM noreply@tax.service.gov.uk');
        
        if (!$emails) {
            $this->logger->info('No unread P6/P9 emails found');
            return $notices;
        }
        
        foreach ($emails as $emailId) {
            try {
                $header = imap_headerinfo($this->imapConnection, $emailId);
                $subject = $header->subject ?? '';
                
                if (!$this->isP6P9Email($subject)) {
                    continue;
                }
                
                $body = $this->getEmailBody($emailId);
                $parsed = $this->monitor->parseP6P9Email($body);
                
                if ($parsed !== null) {
                    $this->monitor->storeNotice($parsed['nino'], $parsed);
                    $notices[] = $parsed;
                    
                    $this->logger->info("Parsed unread P6/P9 for {$parsed['nino']}: {$parsed['taxCode']}");
                    
                    @imap_setflag_full($this->imapConnection, (string)$emailId, "\\Seen \\Flagged");
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to process email {$emailId}: " . $e->getMessage());
                continue;
            }
        }
        
        return $notices;
    }
    
    /**
     * Check if email subject indicates P6/P9 notice
     */
    protected function isP6P9Email(string $subject): bool
    {
        $keywords = [
            'tax code',
            'P6',
            'P9',
            'coding notice',
            'PAYE coding',
            'new tax code',
            'tax code change'
        ];
        
        $subject = strtolower($subject);
        
        foreach ($keywords as $keyword) {
            if (stripos($subject, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get email body content
     */
    protected function getEmailBody(int $emailId): string
    {
        $structure = @imap_fetchstructure($this->imapConnection, $emailId);
        
        if (!$structure) {
            return @imap_body($this->imapConnection, $emailId) ?: '';
        }
        
        if (!isset($structure->parts)) {
            // Simple email (no parts)
            return @imap_body($this->imapConnection, $emailId) ?: '';
        }
        
        // Multipart email - get text part
        foreach ($structure->parts as $partNum => $part) {
            // Text part
            if ($part->type == 0) {
                $body = @imap_fetchbody($this->imapConnection, $emailId, (string)($partNum + 1));
                
                // Handle encoding
                if ($part->encoding == 3) { // Base64
                    $body = base64_decode($body);
                } elseif ($part->encoding == 4) { // Quoted-printable
                    $body = quoted_printable_decode($body);
                }
                
                return $body ?: '';
            }
        }
        
        // Fallback
        return @imap_body($this->imapConnection, $emailId) ?: '';
    }
    
    /**
     * Get connection status
     */
    public function isConnected(): bool
    {
        return $this->imapConnection !== null && $this->imapConnection !== false;
    }
    
    /**
     * Get mailbox info
     */
    public function getMailboxInfo(): ?array
    {
        if (!$this->imapConnection) {
            return null;
        }
        
        $check = @imap_check($this->imapConnection);
        
        if (!$check) {
            return null;
        }
        
        return [
            'date' => $check->Date,
            'driver' => $check->Driver,
            'mailbox' => $check->Mailbox,
            'messages' => $check->Nmsgs,
            'recent' => $check->Recent
        ];
    }
    
    /**
     * Close connection
     */
    public function disconnect(): void
    {
        if ($this->imapConnection) {
            @imap_close($this->imapConnection);
            $this->imapConnection = null;
            $this->logger->info('Disconnected from email server');
        }
    }
    
    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
