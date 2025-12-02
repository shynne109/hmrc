<?php

namespace HMRC\PAYE\P6P9;

/**
 * Helper class to bridge P6P9Monitor with P6Notice/P9Notice classes
 * 
 * Provides conversion between the array-based Monitor format
 * and the object-based Notice classes.
 */
class P6P9Converter
{
    /**
     * Convert P6P9Monitor array data to P6Notice object
     * 
     * @param array $data Array from P6P9Monitor
     * @param string $taxOfficeNumber Default tax office number
     * @param string $taxOfficeReference Default tax office reference
     * @return P6Notice|null
     */
    public static function toP6Notice(
        array $data, 
        string $taxOfficeNumber = '000',
        string $taxOfficeReference = 'UNKNOWN'
    ): ?P6Notice {
        try {
            // Required fields
            $nino = $data['nino'] ?? null;
            $taxCode = $data['taxCode'] ?? $data['newTaxCode'] ?? null;
            $effectiveDate = $data['effectiveDate'] ?? date('Y-m-d');
            $forename = $data['forename'] ?? $data['firstName'] ?? 'Unknown';
            $surname = $data['surname'] ?? $data['lastName'] ?? 'Employee';
            
            if (!$nino || !$taxCode) {
                return null;
            }
            
            $notice = new P6Notice(
                $nino,
                $taxCode,
                $effectiveDate,
                $data['taxOfficeNumber'] ?? $taxOfficeNumber,
                $data['taxOfficeReference'] ?? $taxOfficeReference,
                $forename,
                $surname
            );
            
            // Set optional fields
            if (!empty($data['previousTaxCode'])) {
                $notice->setPreviousTaxCode($data['previousTaxCode']);
            }
            
            // Handle operatesOn -> taxCodeBasis
            $operatesOn = $data['operatesOn'] ?? $data['taxCodeBasis'] ?? 'cumulative';
            if (in_array($operatesOn, ['week1month1', 'w1', 'm1', 'week1', 'month1'])) {
                $notice->setTaxCodeBasis(P6Notice::BASIS_WEEK1_MONTH1);
            }
            
            if (!empty($data['noticeType'])) {
                if (stripos($data['noticeType'], 'P6B') !== false) {
                    $notice->setNoticeType(P6Notice::NOTICE_TYPE_P6B);
                }
            }
            
            if (!empty($data['payrollId'])) {
                $notice->setPayrollId($data['payrollId']);
            }
            
            if (!empty($data['changeReason']) || !empty($data['reason'])) {
                $notice->setChangeReason($data['changeReason'] ?? $data['reason']);
            }
            
            if (!empty($data['source'])) {
                $notice->addAdditionalData('source', $data['source']);
            }
            
            // Mark as processed if already notified
            if (!empty($data['notified']) && $data['notified'] === true) {
                $notice->markAsProcessed();
            }
            
            return $notice;
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Convert P6P9Monitor array data to P9Notice object
     */
    public static function toP9Notice(
        array $data,
        string $taxOfficeNumber = '000',
        string $taxOfficeReference = 'UNKNOWN'
    ): ?P9Notice {
        try {
            // Required fields
            $nino = $data['nino'] ?? null;
            $taxCode = $data['taxCode'] ?? null;
            $effectiveDate = $data['effectiveDate'] ?? date('Y-m-d');
            $forename = $data['forename'] ?? $data['firstName'] ?? 'Unknown';
            $surname = $data['surname'] ?? $data['lastName'] ?? 'Employee';
            
            if (!$nino || !$taxCode) {
                return null;
            }
            
            $notice = new P9Notice(
                $nino,
                $taxCode,
                $effectiveDate,
                $data['taxOfficeNumber'] ?? $taxOfficeNumber,
                $data['taxOfficeReference'] ?? $taxOfficeReference,
                $forename,
                $surname
            );
            
            // Set optional fields
            if (!empty($data['previousTaxCode'])) {
                $notice->setPreviousTaxCode($data['previousTaxCode']);
            }
            
            // Handle operatesOn -> taxCodeBasis  
            $operatesOn = $data['operatesOn'] ?? $data['taxCodeBasis'] ?? 'cumulative';
            if (in_array($operatesOn, ['week1month1', 'w1', 'm1', 'week1', 'month1'])) {
                $notice->setTaxCodeBasis(P9Notice::BASIS_WEEK1_MONTH1);
            }
            
            if (!empty($data['payrollId'])) {
                $notice->setPayrollId($data['payrollId']);
            }
            
            if (!empty($data['source'])) {
                $notice->addAdditionalData('source', $data['source']);
            }
            
            // Mark as processed if already notified
            if (!empty($data['notified']) && $data['notified'] === true) {
                $notice->markAsProcessed();
            }
            
            return $notice;
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Convert P6Notice to Monitor array format
     */
    public static function fromP6Notice(P6Notice $notice): array
    {
        return [
            'nino' => $notice->getNino(),
            'taxCode' => $notice->getNewTaxCode(),
            'previousTaxCode' => $notice->getPreviousTaxCode(),
            'effectiveDate' => $notice->getEffectiveDate(),
            'noticeType' => $notice->getNoticeType(),
            'operatesOn' => $notice->isNonCumulative() ? 'week1month1' : 'cumulative',
            'forename' => $notice->getForename(),
            'surname' => $notice->getSurname(),
            'payrollId' => $notice->getPayrollId(),
            'taxOfficeNumber' => $notice->getTaxOfficeNumber(),
            'taxOfficeReference' => $notice->getTaxOfficeReference(),
            'notified' => $notice->isProcessed(),
            'notifiedAt' => $notice->getProcessedAt(),
            'source' => $notice->getAdditionalData()['source'] ?? 'p6notice',
        ];
    }
    
    /**
     * Convert P9Notice to Monitor array format
     */
    public static function fromP9Notice(P9Notice $notice): array
    {
        return [
            'nino' => $notice->getNino(),
            'taxCode' => $notice->getTaxCode(),
            'previousTaxCode' => $notice->getPreviousTaxCode(),
            'effectiveDate' => $notice->getEffectiveDate(),
            'noticeType' => $notice->getNoticeType(),
            'operatesOn' => $notice->isNonCumulative() ? 'week1month1' : 'cumulative',
            'forename' => $notice->getForename(),
            'surname' => $notice->getSurname(),
            'payrollId' => $notice->getPayrollId(),
            'taxOfficeNumber' => $notice->getTaxOfficeNumber(),
            'taxOfficeReference' => $notice->getTaxOfficeReference(),
            'notified' => $notice->isProcessed(),
            'notifiedAt' => $notice->getProcessedAt(),
            'source' => $notice->getAdditionalData()['source'] ?? 'p9notice',
        ];
    }
    
    /**
     * Convert Monitor array data to appropriate Notice type based on noticeType field
     */
    public static function toNotice(
        array $data,
        string $taxOfficeNumber = '000',
        string $taxOfficeReference = 'UNKNOWN'
    ): P6Notice|P9Notice|null {
        $noticeType = strtoupper($data['noticeType'] ?? 'P6');
        
        if (str_starts_with($noticeType, 'P9')) {
            return self::toP9Notice($data, $taxOfficeNumber, $taxOfficeReference);
        }
        
        return self::toP6Notice($data, $taxOfficeNumber, $taxOfficeReference);
    }
    
    /**
     * Bulk convert Monitor array data to P6Notice collection
     */
    public static function toP6Collection(
        array $dataArray,
        string $taxOfficeNumber = '000',
        string $taxOfficeReference = 'UNKNOWN'
    ): P6NoticeCollection {
        $collection = new P6NoticeCollection();
        
        foreach ($dataArray as $data) {
            $notice = self::toP6Notice($data, $taxOfficeNumber, $taxOfficeReference);
            if ($notice !== null) {
                $collection->add($notice);
            }
        }
        
        return $collection;
    }
    
    /**
     * Bulk convert Monitor array data to P9Notice collection
     */
    public static function toP9Collection(
        array $dataArray,
        string $taxOfficeNumber = '000',
        string $taxOfficeReference = 'UNKNOWN'
    ): P9NoticeCollection {
        $collection = new P9NoticeCollection();
        
        foreach ($dataArray as $data) {
            $notice = self::toP9Notice($data, $taxOfficeNumber, $taxOfficeReference);
            if ($notice !== null) {
                $collection->add($notice);
            }
        }
        
        return $collection;
    }
}
