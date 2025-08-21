<?php


namespace HMRC;

use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use XMLWriter;

class GovTalk implements LoggerAwareInterface
{
    const QUALIFIER_ACKNOWLEDGEMENT = 'acknowledgement';
    const VERSION = '1.0';

    /* Server related variables. */

    /**
     * A Guzzle client for making API calls
     */
    private Client $httpClient;

    private string $govTalkServer;

    protected string $govTalkSenderId;

    /**
     * GovTalk sender password.
     */
    protected string $govTalkPassword;

    /* General envelope related variables. */

    /**
     * Additional XSI SchemaLocation URL.  Default is null, no additional schema.
     */
    private ?string $additionalXsiSchemaLocation = null;

    /**
     * GovTalk test flag.  Default is 0, a real message.
     */
    private string $govTalkTest = '0';

    private ?\DateTime $timestamp = null;

    /**
     * Body of the message to be sent.
     *
     * @var mixed Can either be of type XMLWriter, or a string.
     */
    private $messageBody;


    /* MessageDetails related variables */

    /**
     * GovTalk message Class.
     *
     * @var string
     */
    private $messageClass;

    /**
     * GovTalk message Qualifier.
     *
     * @var string
     */
    private $messageQualifier;

    /**
     * GovTalk message Function.  Default is null, no specified function.
     *
     * @var string
     */
    private $messageFunction = null;

    /**
     * GovTalk message CorrelationID.  Default is null, no correlation ID.
     *
     * @var string
     */
    private $messageCorrelationId = null;

    /**
     * GovTalk message Transformation.  Default is null, return in standard XML.
     *
     * @var string
     */
    private $messageTransformation = 'XML';


    /* SenderDetails related variables. */

    /**
     * GovTalk SenderDetail EmailAddress.  Default is null, no email address.
     *
     * @var string
     */
    private $senderEmailAddress = null;

    /**
     * GovTalk message authentication type.
     *
     * @var string
     */
    private $messageAuthType;


    /* Keys related variables. */

    /**
     * GovTalk keys array.
     *
     * @var array
     */
    private $govTalkKeys = array();


    /* Channel routing related variables. */

    /**
     * GovTalk message channel routing array.
     *
     * @var array
     */
    private $messageChannelRouting = [];


    /* Target details related variables. */

    /**
     * GovTalk target details / organisations array.
     *
     * @var array
     */
    private $messageTargetDetails = array();


    /* Full request/response data variables. */

    /**
     * Full request data in string format (raw XML).
     *
     * @var string
     */
    protected $fullRequestString;

    /**
     * Full return data in string format (raw XML).
     *
     * @var string
     */
    protected $fullResponseString;

    /**
     * Full return data in object format (SimpleXML).
    *
    * @var \SimpleXMLElement
     */
    protected $fullResponseObject;


    /* Error handling variables. */

    /**
     * An array containing all reported errors.
     *
     * The error array is stored and returned in the following format, one
     * one element for every error which has been reported:
     *   time => The unix timestamp (with microseconds) that this error was generated.
     *   code => A short error code. Defined by the function adding the error and not globally.
     *   message => A more descriptive error message. Defined by the function adding the error, but more verbose.
     *   function => The name of the calling function. (Optional.)
     *
     * @since 0.4
     * @var array
     */
    protected $errorArray = array();


    /* System / internal variables. */

    /**
     * Transaction ID of the last message sent / received.
     *
     * @var string
     */
    private $transactionId = null;

    /**
     * Flag indicating if the outgoing and incoming XML should be validated
     * against the XML schema. By default these checks will be made.
     *
     * @var boolean
     */
    private $schemaValidation = true;

    /**
     * PSR-3 logger – defaulting to `NullLogger`.
     * @see GovTalk::setLogger()
     */
    private LoggerInterface $logger;

    /**
     * @var bool    Requested by HMRC that this does *not* happen, but not prohibited by the
     *              general GovTalk-Envelope spec. Potentially useful for other departments?
     */
    private bool $autoAppendOwnChannelRouting = true;
    /**
     * Captured XML schema validation errors for last request/response validation.
     */
    private array $schemaValidationErrors = [];

    /**
     * Instance constructor.
     *
     * @param string $govTalkServer GovTalk server URL.
     * @param string $govTalkSenderId GovTalk sender ID.
     * @param string $govTalkPassword GovTalk password.
     * @param Client $httpClient A Guzzle client for making API calls
     */
    public function __construct(
        $govTalkServer,
        $govTalkSenderId,
        $govTalkPassword,
        ?Client $httpClient = null
    ) {
        $this->setGovTalkServer($govTalkServer);
        $this->govTalkSenderId = $govTalkSenderId;
        $this->govTalkPassword = $govTalkPassword;
        $this->httpClient = $httpClient ?: $this->getDefaultHttpClient();
        $this->logger = new NullLogger(); // Call setLogger() to log.
    }

    /* Public methods. */


    /* Error handling funtions. */

    /**
     * Adds a new error to the end of the error array.
     *
     * @since 0.4
     * @see $errorArray
     * @param string $errorCode An error code identifying this error being logged.
     *     While not globally unique, care should be taken to make this useful.
     * @param string $errorMessage An error message in plain text. This might be
     *     displayed to the user by applications, so should be something pretty descriptive. (Optional.)
     * @param string $function The function which generated this error. While this is optional,
     *     and might not be very helpful (depending on the error), it's easy to add with __FUNCTION__. (Optional.)
     * @return boolean This function always returns true.
     */
    protected function logError($errorCode, $errorMessage = null, $function = null)
    {
        $this->errorArray[] = array(
            'time' => microtime(true),
            'code' => $errorCode,
            'message' => $errorMessage,
            'function' => $function
        );
        return true;
    }

    /**
     * Returns the number of errors which have been logged in the error array
     * since this instance was initialised, or the error array was last reset.
     *
     * @since 0.4
     * @see logError(), clearErrors(), getErrors()
     * @return int The number of errors since the error array was last reset.
     */
    public function errorCount()
    {
        return count($this->errorArray);
    }

    /**
     * Returns the full error array.
     *
     * @since 0.4
     * @see getLastError(), $errorArray
     * @return array The complete error array.
     */
    public function getErrors()
    {
    return $this->errorArray; // bug fix: property, not method
    }

    /**
     * Returns the last error pushed onto the error array.
     *
     * @since 0.4
     * @see getErrors(), $errorArray
     * @return array The last element pushed onto the error array.
     */
    public function getLastError()
    {
        return end($this->errorArray);
    }

    /**
     * Clears all errors out of the error array.
     *
     * @since 0.4
     * @see $errorArray
     * @return boolean This function always returns true.
     */
    public function clearErrors()
    {
        $this->errorArray = array();
        return true;
    }


    /* Logical / operational / conditional methods */

    /**
     * Tests if a response has errors.  Should be checked before further
     * operations are carried out on the returned object.
     *
     * @return boolean True if errors are present, false if not.
     */
    public function responseHasErrors()
    {
        if (isset($this->fullResponseObject)) {
            if (isset($this->fullResponseObject->GovTalkDetails->GovTalkErrors)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /* System / internal get methods. */

    /**
     * Returns the transaction ID used in the last message sent / received.
     *
     * @return string Transaction ID.
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * Returns the full XML request from the last Gateway request, if there is
     * one.
     *
     * @return string|bool The full text request from the Gateway, or false if this isn't set.
     */
    public function getFullXMLRequest()
    {
        return $this->fullRequestString ?? false;
    }

    /**
     * Returns the full XML response from the last Gateway request, if there is
     * one.
     *
     * @return mixed The full text response from the Gateway, or false if this isn't set.
     */
    public function getFullXMLResponse()
    {
        if (isset($this->fullResponseString)) {
            return $this->fullResponseString;
        } else {
            return false;
        }
    }


    /* Response data get methods */

    /**
     * Returns the Gateway response message qualifier of the last response
     * received, if there is one.
     *
     * @return string|bool The response qualifier, or false if there is no response.
     */
    public function getResponseQualifier()
    {
        if (!isset($this->fullResponseObject)) {
            return false;
        }
        $header = $this->fullResponseObject->Header ?? null;
        if ($header === null) { return false; }
        $details = $header->MessageDetails ?? null;
        if ($details === null) { return false; }
        $qual = $details->Qualifier ?? null;
        if ($qual === null || (string)$qual === '') { return false; }
        return (string)$qual;
    }

    /**
     * Returns the Gateway timestamp of the last response received, if there is
     * one.
     *
     * @return integer The Gateway timestamp as a unix timestamp, or false if this isn't set.
     */
    public function getGatewayTimestamp()
    {
        if (isset($this->fullResponseObject)) {
            return strtotime((string) $this->fullResponseObject->Header->MessageDetails->GatewayTimestamp);
        } else {
            return false;
        }
    }

    /**
     * Returns the correlation ID issued by the Gateway in the last response, if
     * there was one.  Once an ID has been assigned by the Gateway, any
     * subsequent communications regarding a message must include it.
     *
     * @return string|bool The Correlation ID (pattern: [0-9A-F]{0,32}), or false if this isn't set.
     */
    public function getResponseCorrelationId()
    {
    if (!isset($this->fullResponseObject)) { return false; }
    $header = $this->fullResponseObject->Header ?? null; if (!$header) { return false; }
    $details = $header->MessageDetails ?? null; if (!$details) { return false; }
    if (!isset($details->CorrelationID) || (string)$details->CorrelationID === '') { return false; }
    return (string)$details->CorrelationID;
    }

    /**
     * Returns information from the Gateway ResponseEndPoint including recomended
     * retry times, if there is one.
     *
     * @return array|bool   The Gateway 'endpoint' and retry 'interval' (assoc array keys),
     *                      or false if this isn't set.
     */
    public function getResponseEndpoint()
    {
        if (isset($this->fullResponseObject)) {
            if (isset($this->fullResponseObject->Header->MessageDetails->ResponseEndPoint)) {
                if (isset($this->fullResponseObject->Header->MessageDetails->ResponseEndPoint['PollInterval'])) {
                    $pollInterval = (string)$this->fullResponseObject
                        ->Header->MessageDetails->ResponseEndPoint['PollInterval'];
                } else {
                    $pollInterval = null;
                }
                $endpoint = (string)$this->fullResponseObject->Header->MessageDetails->ResponseEndPoint;
                return [
                    'endpoint' => $endpoint,
                    'interval' => $pollInterval
                ];
            }
        }

        return false;
    }


    /**
     * Returns an array of errors, if any are present.  Errors can be 'fatal',
     * 'recoverable', 'business' or 'warning'.  If no errors are found this
     * function will return false.
     *
     * @return mixed Array of errors, or false if there are no errors.
     */
    public function getResponseErrors()
    {
        
        if ($this->responseHasErrors()) {
            $errorArray = array(
                'fatal' => array(),
                'recoverable' => array(),
                'business' => array(),
                'warning' => array(),
                'schema' => array()
            );
            foreach ($this->fullResponseObject->GovTalkDetails->GovTalkErrors->Error as $responseError) {
                $errorDetails = array(
                    'number' => (string) $responseError->Number,
                    'text' => (string) $responseError->Text
                );
                if (isset($responseError->Location) && (string) $responseError->Location !== '') {
                    $errorDetails['location'] = (string) $responseError->Location;
                }
                $errorArray[(string) $responseError->Type][] = $errorDetails;
            }
            if(isset($this->fullResponseObject->Body->ErrorResponse)){
                foreach ($this->fullResponseObject->Body->ErrorResponse->Error as $responseError) {
                    $errorDetails = array(
                        'number' => (string) $responseError->Number,
                        'text' => (string) $responseError->Text
                    );
                    if (isset($responseError->Application->Messages->DeveloperMessage)) {
                        $errorDetails['developerMessage'] = (string)$responseError->Application->Messages->DeveloperMessage;
                    }
                    if (isset($responseError->Location) && (string)$responseError->Location !== '') {
                        $errorDetails['location'] = (string)$responseError->Location;
                    }
                    $errorArray[(string)$responseError->Type][] = $errorDetails;
                }
            }
            return $errorArray;
        } else {
            return false;
        }
    }

    /**
     * Returns the contents of the response Body section, removing all GovTalk
     * Message Envelope wrappers, as a SimpleXML object.
     *
     * @return mixed The message body as a SimpleXML object, or false if this isn't set.
     */
    public function getResponseBody()
    {
        if (isset($this->fullResponseObject)) {
            return $this->fullResponseObject->Body;
        } else {
            return false;
        }
    }


    /* General envelope related set methods. */

    /**
     * Change the URL used to talk to the Government Gateway from that set during
     * the instance instantiation. Very handy when required to poll a different
     * URL for the result of a submission request.
     *
     * @param string $govTalkServer GovTalk server URL.
     */
    public function setGovTalkServer($govTalkServer)
    {
        $this->govTalkServer = $govTalkServer;
    }

    /**
     * An additional SchemaLocation for use in the GovTalk headers.  This URL
     * should be the location of an additional xsd defining the body segment.
     * By default if an additional schema is set then both incoming and outgoing
     * XML data will be validated against it.  This can be disabled by passing
     * false as the second argument when setting the schema.
     *
     * @param string $schemaLocation URL location of additional xsd.
     * @param boolean $validate True to turn validation on, false to turn it off.
     * @return boolean True if the URL is valid and set, false if it's invalid (and therefore not set).
     */
    public function setSchemaLocation($schemaLocation, $validate = null)
    {
        if (preg_match('/^https?:\/\/[\w-.]+\.gov\.uk/', $schemaLocation)) {
            $this->additionalXsiSchemaLocation = $schemaLocation;
            if ($validate !== null) {
                $this->setSchemaValidation($validate);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Switch off (or on) schema validation of outgoing and incoming XML data
     * against the additional XML schema.
     *
     * @param boolean $validationFlag True to turn validation on, false to turn it off.
     * @return boolean True if the validation is set, false if setting the validation failed.
     */
    public function setSchemaValidation($validate)
    {
        if (is_bool($validate)) {
            $this->schemaValidation = $validate;
            return true;
        }

        return false;
    }

    /**
     * Sets the test flag.  A flag value of true tells the Gateway this message
     * is a test, false (default) tells it this is a live message.
     *
     * @param boolean $testFlag The value to set the test flag to.
     * @return boolean True if the flag is set successfully, false otherwise.
     */
    public function setTestFlag($testFlag)
    {
        if (is_bool($testFlag)) {
            if ($testFlag === true) {
                $this->govTalkTest = '1';
            } else {
                $this->govTalkTest = '0';
            }
            return true;
        }

        return false;
    }

    /**
     * Gets the current status of the test flag.
     *
     * @return boolean the current state of the test flag.
     */
    public function getTestFlag()
    {
        return $this->govTalkTest == '1';
    }

    /**
     * Sets the message body. Message body can be either of type XMLWriter, or a
     * static string.  The message body will be included between the Body tags
     * of the GovTalk envelope just as it's set and therefore must be valid XML.
     *
     * Providing an XML schema URL will cause the function to validate the
     * message body against the schema prior to setting it. If no schema is
     * supplied no checks will be made at this stage.
     *
     * @param mixed $messageBody The XML body of the GovTalk message.
     * @param string $xmlSchema The URL of an XML schema to check the XML body against.
     * @return boolean True if the body is valid and set, false if it's invalid (and therefore not set).
     */
    public function setMessageBody($messageBody, $xmlSchema = null): bool
    {
        if (!is_string($messageBody) && !($messageBody instanceof \XMLWriter)) {
            return false;
        }

        if ($xmlSchema !== null) {
            $validate = new DOMDocument();
            if (is_string($messageBody)) {
                $validate->loadXML($messageBody);
            } else {
                $validate->loadXML($messageBody->outputMemory());
            }
            $this->clearSchemaValidationErrors();
            libxml_use_internal_errors(true);
            $valid = $validate->schemaValidate($xmlSchema);
            if (!$valid) {
                $this->captureLibxmlErrors();
            }
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            if ($valid) {
                $this->messageBody = $messageBody;
                return true;
            }
            return false;
        }

        $this->messageBody = $messageBody;
        return true;
    }


    /* MessageDetails related set methods. */

    /**
     * Sets the message Class for use in MessageDetails header.
     *
     * @param string $messageClass The class to set.
     * @return boolean True if the Class is valid and set, false if it's invalid (and therefore not set).
     */
    public function setMessageClass($messageClass)
    {
        $messageClassLength = strlen($messageClass);
        if (($messageClassLength > 4) && ($messageClassLength < 32)) {
            $this->messageClass = $messageClass;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sets the message Qualifier for use in MessageDetails header.  The
     * Qualifier may be one of 'request', 'acknowledgement', 'response', 'poll'
     * or 'error'. Any other values will not be set and will return false.
     *
     * @param string $messageQualifier The qualifier to set.
     * @return boolean True if the Qualifier is valid and set, false if it's invalid (and therefore not set).
     */
    public function setMessageQualifier($messageQualifier)
    {
        $messageQualifier = strtolower($messageQualifier);
        switch ($messageQualifier) {
            case 'request':
            case self::QUALIFIER_ACKNOWLEDGEMENT:
            case 'response': // correct spelling
            case 'reponse': // legacy typo accepted
            case 'poll':
            case 'error':
                if ($messageQualifier === 'reponse') { // normalise
                    $messageQualifier = 'response';
                }
                $this->messageQualifier = $messageQualifier;
                return true;
            break;
            default:
                return false;
            break;
        }
    }

    /**
     * Sets the message Function for use in MessageDetails header. This function
     * is designed to be extended by department-specific extensions to validate
     * the possible options for message function, although can be used as-is.
     *
     * @param string $messageFunction The function to set.
     * @return boolean True if the Function is valid and set, false if it's invalid (and therefore not set).
     */
    public function setMessageFunction($messageFunction)
    {
        $this->messageFunction = $messageFunction;
        return true;
    }

    /**
     * Sets the message CorrelationID for use in MessageDetails header.
     *
     * @param string $messageCorrelationId The correlation ID to set.
     * @return boolean True if the CorrelationID is valid and set, false if it's invalid (and therefore not set).
     * @see function getResponseCorrelationId
     */
    public function setMessageCorrelationId($messageCorrelationId)
    {
        if (preg_match('/[0-9A-F]{0,32}/', $messageCorrelationId)) {
            $this->messageCorrelationId = $messageCorrelationId;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sets the message Transformation for use in MessageDetails header. Possible
     * values are 'XML', 'HTML', or 'text'. The default is XML.
     *
     * Note: setting this to anything other than XML will limit the functionality
     * of the GovTalk class and some extensions as they are not currently able to
     * parse HTML or text documents. You are advised against changing this value
     * from the default.
     *
     * @param string $messageCorrelationId The correlation ID to set.
     * @return boolean True if the CorrelationID is valid and set, false if it's invalid (and therefore not set).
     * @see function getResponseCorrelationId
     */
    public function setMessageTransformation($transformation)
    {
        switch ($transformation) {
            case 'XML':
            case 'HTML':
            case 'text':
                $this->messageTransformation = $transformation;
                return true;
            break;
            default:
                return false;
            break;
        }
    }


    /* SenderDetails related set methods. */

    /**
     * Sets the sender email address for use in SenderDetails header.  Note: the
     * validation used when setting an email address here is that specified by
     * the GovTalk 2.0 envelope specifcation and is somewhat limited.
     *
     * @param string $senderEmailAddress The email address to set.
     * @return boolean True if the EmailAddress is valid and set, false if it's invalid (and therefore not set).
     */
    public function setSenderEmailAddress($senderEmailAddress)
    {
        if (preg_match('/[A-Za-z0-9\.\-_]{1,64}@[A-Za-z0-9\.\-_]{1,64}/', $senderEmailAddress)) {
            $this->senderEmailAddress = $senderEmailAddress;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets the currently configured email address.
     *
     * @return string The currently held email address
     */
    public function getSenderEmailAddress()
    {
        return $this->senderEmailAddress;
    }

    /**
     * Sets the type of authentication to use for with the message.  The message
     * type must now be 'clear'. Other values will not be set and will return false.
     * HMRC docs as of 2021 suggest that at least the MD5 type has been retired.
     *
     * @param string $messageAuthType The type of authentication to set.
     * @return boolean True if the authentication type is valid and set, false if it's invalid (and therefore not set).
     */
    public function setMessageAuthentication($messageAuthType)
    {
        switch ($messageAuthType) {
            case 'alternative':
            case 'clear':
                $this->messageAuthType = $messageAuthType;
                return true;
            break;
            default:
                return false;
            break;
        }
    }

    /**
     * Gets the current value for authentication type
     *
     * @return string The current authentication method
     */
    public function getMessageAuthentication()
    {
        return $this->messageAuthType;
    }


    /* Channel routing related methods. */

    /**
     * Sets a channel route, replacing any that exist including this library's default. Implicitly
     * turns off this library's feature of adding its own <ChannelRouting/> element, as this is
     * incompatible with e.g. HMRC's preferred submission format according to HMRC Software
     * Developer Support Team as of November '21.
     *
     * @param string        $uri    'URI' is a misnomer for at least some departments. For example
     *                              HMRC expect this to be your 4 digit Vendor ID and nothing else.
     * @param string|null   $softwareName
     * @param string|null   $softwareVersion
     * @param array|null    $id Seems to require an assoc array which 'type' and 'value' keys, if set.
     * @param mixed         $timestamp
     * @return bool Whether route was valid and set. If not, routes will be left empty.
     */
    public function setChannelRoute(
        string $uri,
        ?string $softwareName = null,
        ?string $softwareVersion = null,
    ?array $id = null,
        $timestamp = null
    ): bool {
        $this->setAutoAppendOwnChannelRouting(false);

        $this->messageChannelRouting = [];

        return $this->addChannelRoute($uri, $softwareName, $softwareVersion, $id, $timestamp);
    }

    /**
     * Adds a channel routing element to the message.  Channel routes should be
     * added in order by every application which the message has passed through
     * prior to being sent to the Gateway.  php-govtalk does not support name
     * elements in channel routing.  If not defined the timestamp element will
     * automatically be added at the moment the route is added.  Any optional
     * arguments may be skipped by passing null as that argument.
     *
     * Applications using php-govtalk may add at least one
     * additional channel route before sending a message to the Gateway.
     * However, contrary to the guidance in previous versions of this library
     * and the XML spec, HMRC have stated that for their submissions they expect
     * and prefer only a single <ChannelRouting/> element. You should therefore
     * check in with the department you are sending data to and choose between
     * this method and {@see setChannelRoute()} accordingly.
     *
     * Note: When using *this* method, php-govtalk will add itself as the last route in the chain.
     * This is to identify the library to the Gateway and to assist in tracking
     * down issues caused by the library itself.
     *
     * @param string $uri The URI of the owner of the process being added to the route.
     * @param string $softwareName The name of the software generating this route entry.
     * @param string $softwareVersion The version number of the software generating this route entry.
     * @param array $id An array of IDs (themselves array of 'type' and 'value') to add as array elements.
     * @param string $timestamp Representing the time this route processed the message (xsd:dateTime format).
     * @param boolean $force If true the route already exists check is not carried out
     *     and the target is added regardless of duplicates. (Defaults to false.)
     * @return boolean True if the route is valid and added, false if it's not valid (and therefore not added).
     */
    public function addChannelRoute(
        $uri,
        $softwareName = null,
        $softwareVersion = null,
    ?array $id = null,
        $timestamp = null,
        $force = false
    ) {
        if (is_string($uri)) {
            $newRoute = array('uri' => $uri);
            if ($softwareName !== null) {
                $newRoute['product'] = $softwareName;
            }
            if ($softwareVersion !== null) {
                $newRoute['version'] = $softwareVersion;
            }
            if ($id !== null && is_array($id)) {
                foreach ($id as $idElement) {
                    if (is_array($idElement)) {
                        $newRoute['id'][] = $idElement;
                    }
                }
            }
            if (($timestamp !== null) && ($parsedTimestamp = strtotime($timestamp))) {
                $newRoute['timestamp'] = date('c', $parsedTimestamp);
            } else {
                $newRoute['timestamp'] = date('c');
            }
            if ($force === false) {
                $matchedChannel = false;
                foreach ($this->messageChannelRouting as $channelRoute) {
                    if (($channelRoute['product'] == $newRoute['product']) &&
                        ($channelRoute['version'] == $newRoute['version'])
                    ) {
                        $matchedChannel = true;
                        break;
                    }
                }
                if (!$matchedChannel) {
                    $this->messageChannelRouting[] = $newRoute;
                }
                return true;
            }

            $this->messageChannelRouting[] = $newRoute;
            return true;
        }

        return false;
    }


    /* Keys related methods. */

    /**
     * Add a key-value pair to the set of keys to be sent with the message as
     * part of the GovTalkDetails element.
     *
     * @param string $keyType The key type (type attribute).
     * @param string $keyValue The key value.
     * @return boolean True if the key is valid and added, false if it's not valid (and therefore not added).
     */
    public function addMessageKey($keyType, $keyValue)
    {
        if (is_string($keyType) && $keyValue != '') {
            $this->govTalkKeys[] = array(
                'type' => $keyType,
                'value' => $keyValue
            );
            return true;
        } else {
            return false;
        }
    }

    /**
     * Remove a key-value pair from the set of keys to be sent with the message
     * as part of the GovTalkDetails element.
     *
     * Searching is done primarily on key type (type attribute) and all keys with
     * a corresponding type attribute are deleted.  An optional value argument
     * can be provided, and in these cases only keys with matching key type AND
     * key value will be deleted (but again all keys which meeting these
     * criterion will be deleted).
     *
     * @param string $keyType The key type (type attribute) to be deleted.
     * @param string $keyValue The key value to be deleted.
     * @return integer The number of keys deleted.
     */
    public function deleteMessageKey($keyType, $keyValue = null)
    {
        $deletedCount = 0;
        $possibleMatches = array();
        foreach ($this->govTalkKeys as $arrayKey => $value) {
            if ($value['type'] == $keyType) {
                if (($keyValue !== null) && ($keyValue !== $value['value'])) {
                    continue;
                }
                $deletedCount++;
                unset($this->govTalkKeys[$arrayKey]);
            }
        }

        return $deletedCount;
    }

    /**
     * Removes all GovTalkDetails Key key-value pairs.
     *
     * @return boolean Always returns true.
     */
    public function resetMessageKeys()
    {
        $this->govTalkKeys = array();
        return true;
    }


    /* Target details related methods. */

    /**
     * Add an organisation to the TargetDetails section of the GovTalkDetail
     * element.
     *
     * @param string $targetOrganisation The organisation to be added.
     * @param boolean $force If true the target already exists check is not carried out and the
     *     target is added regardless of duplicates. (Defaults to false.)
     * @return boolean True if the key is valid and added, false if it's not valid (and therefore not added).
     */
    public function addTargetOrganisation($targetOrganisation, $force = false)
    {
        if (($targetOrganisation != '') && (strlen($targetOrganisation) < 65)) {
            if (($force === false) &&
                in_array($targetOrganisation, $this->messageTargetDetails)
            ) {
                return true;
            } else {
                $this->messageTargetDetails[] = $targetOrganisation;
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Remove an organisation from TargetDetails section of the GovTalkDetail
     * element.
     *
     * If more than one organisation matches the given organisation name all are
     * removed.
     *
     * @param string $targetOrganisation The organisation to be deleted.
     * @return integer The number of organisations deleted.
     */
    public function deleteTargetOrganisation($targetOrganisation)
    {
        if (($targetOrganisation != '') && (strlen($targetOrganisation) < 65)) {
            $deletedCount = 0;
            foreach ($this->messageTargetDetails as $key => $organisation) {
                if ($organisation == $targetOrganisation) {
                    $deletedCount++;
                    unset($this->messageTargetDetails[$key]);
                }
            }
            return $deletedCount;
        } else {
            return false;
        }
    }

    /**
     * Removes all GovTalkDetails TargetDetails organisations.
     *
     * @return boolean Always returns true.
     */
    public function resetTargetOrganisations()
    {
        $this->messageTargetDetails = array();
        return true;
    }


    /* Specific generic Gateway requests. */

    /**
     * Sends a generic delete request. By default the request refers to the last
     * stored correlation ID and class, but this behaviour can be over-ridden by
     * providing both correlation ID and class to the method.
     *
     * @param string $govTalkServer The GovTalk server to send the delete request to. May be skipped with a null value.
     * @param string $correlationId The correlation ID to be deleted.
     * @param string $messageClass
     *     The class used when the request which generated the correlation ID was sent to the gateway.
     * @return boolean True if message was successfully deleted from the gateway, false otherwise.
     */
    public function sendDeleteRequest($correlationId = null, $messageClass = null)
    {
        if (($correlationId !== null) && ($messageClass !== null)) {
            if (preg_match('/[0-9A-F]{0,32}/', $correlationId)) {
                $correlationId = $correlationId;
                $messageClass = $messageClass;
            } else {
                return false;
            }
        } else {
            if ($correlationId = $this->getResponseCorrelationId()) {
                $messageClass = $this->messageClass;
            } else {
                return false;
            }
        }

        $this->setMessageClass($messageClass);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('delete');
        $this->setMessageCorrelationId($correlationId);
        $this->setMessageBody('');

        if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Submits and processes a generic list request. By default the request
     * refers to the last stored message class, but this behaviour can be over-
     * ridden by providing a different class to the method.
     *
     * @param string $messageClass The class of request to list
     */
    public function sendListRequest($messageClass = null)
    {
        if ($messageClass === null) {
            $messageClass = $this->messageClass;
        }

        $this->setMessageClass($messageClass);
        $this->setMessageQualifier('request');
        $this->setMessageFunction('list');
        $this->setMessageCorrelationId('');
        $this->setMessageBody('');

        if ($this->sendMessage() && ($this->responseHasErrors() === false)) {
            if ((string) $this->fullResponseObject->Header->MessageDetails->Qualifier === 'response') {
                $returnArray = array();
                foreach ($this->fullResponseObject->Body->StatusReport->StatusRecord as $reportNode) {
                    preg_match(
                        '#(\d{2})/(\d{2})/(\d{4}) (\d{2}):(\d{2}):(\d{2})#',
                        $reportNode->TimeStamp,
                        $timeChunks
                    );
                    $returnArray[] = array(
                        'timestamp' => mktime(
                            $timeChunks[4],
                            $timeChunks[5],
                            $timeChunks[6],
                            $timeChunks[2],
                            $timeChunks[1],
                            $timeChunks[3]
                        ),
                        'correlation' => (string) $reportNode->CorrelationID,
                        'transaction' => (string) $reportNode->TransactionID,
                        'status' => (string) $reportNode->Status
                    );
                }
                return $returnArray;
            }
        }
        return false;
    }


    /* Message sending related methods. */

    /**
     * Sends the message currently stored in the object to the currently defined
     * GovTalkServer and parses the response for use later.
     *
     * Note: the return value of this method does not reflect the success of the
     * data transmitted to the Gateway, but that the message was transmitted
     * correctly and that a response was received.  Applications must query
     * the response methods to discover more informationa about the data recieved
     * in the Gateway reply.
     *
     * @param mixed cRequestString If not null this will be used as the message payload
     * @return bool True if the message was successfully submitted to the Gateway and a response was received.
     */
    public function sendMessage($cRequestString = null): bool
    {
        if ($cRequestString !== null) {
            $this->fullRequestString = $cRequestString;
        } else {
            $this->fullRequestString = $this->packageGovTalkEnvelope();
        }
        if ($this->fullRequestString) {
            $this->fullResponseString = $this->fullResponseObject = null;

            // Log the outgoing message
            $this->log($this->transactionId, 'request', $this->fullRequestString);

            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8'
            ];

            try {
                $httpResponse = $this->httpClient->post(
                    $this->govTalkServer,
                    [
                        'body' => $this->fullRequestString,
                        'headers' => $headers,
                    ],
                );
            } catch (TransferException $exception) {
                $this->logError($exception->getCode(), $exception->getMessage());

                return false;
            }

            $gatewayResponse = (string)$httpResponse->getBody();

            // Log the incoming message
            $this->log($this->transactionId, 'response', $gatewayResponse);

            $this->fullResponseString = $gatewayResponse;
            $validXMLResponse = false;
            if ($this->messageTransformation === 'XML') {
                if (isset($this->additionalXsiSchemaLocation) && ($this->schemaValidation == true)) {
                    $xsiSchemaHeaders = @get_headers($this->additionalXsiSchemaLocation);
                    if ($xsiSchemaHeaders[0] !== 'HTTP/1.1 404 Not Found') {
                        $validate = new DOMDocument();
                        $validate->loadXML($this->fullResponseString);
                        if ($validate->schemaValidate($this->additionalXsiSchemaLocation)) {
                            $validXMLResponse = true;
                        }
                    } else {
                        return false;
                    }
                } else {
                    $validXMLResponse = true;
                }
            }
            if ($validXMLResponse === true) {
                // TODO props suppress warnings and bubble errors through in a more helpful way.
                // Return false when there are major parse errors.
                $this->fullResponseObject = simplexml_load_string($gatewayResponse);
            }
            return true;
        }

        return false;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Inject a custom HTTP client (useful for tests & dependency injection).
     */
    public function setHttpClient(Client $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Retrieve the configured HTTP client.
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * When using the Local Test Service (LTS) you need to set a
     * time stamp in the Message Details element. Typically you would pass in
     * a `new DateTime()` (i.e. now) but for some uses cases – such as demonstrating
     * a simulated request for Recognition of your software – you may need to pass
     * in a historic date.
     *
     * When sending Live or External Test Service data the key must *not* be set.
     *
     * @param \DateTime|null $timestamp Timestamp of request, for non-live scenarios.
     */
    public function setTimestamp(?\DateTime $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @param bool $autoAppendOwnChannelRouting
     */
    public function setAutoAppendOwnChannelRouting(bool $autoAppendOwnChannelRouting): void
    {
        $this->autoAppendOwnChannelRouting = $autoAppendOwnChannelRouting;
    }

    /* Protected methods. */

    /**
     * @param string $transactionId
     * @param string $direction 'request' or 'response'
     * @param string $message
     */
    protected function log(?string $transactionId = null, ?string $direction = null, ?string $message = null): void
    {
        $this->logger->info($message, [
            'direction' => $direction,
            'transactionId' => $transactionId,
        ]);
    }

    /**
     * This method is designed to be over-ridden by extending classes which
     * require an alternative authentication algorithm.
     *
     * These methods should take the transaction ID as an argument and return
     * an array of 'method' => the method string to use in IDAuthentication->
     * Authentication->Method, 'token' => the token to use in IDAuthentication->
     * Authentication->Value, or false on failure.
     *
     * @param string $transactionId Transaction ID to use generating the token.
     * @return mixed The authentication array, or false on failure.
     */
    protected function generateAlternativeAuthentication($transactionId)
    {
        return false;
    }

    /**
     * This method is designed to be over-ridden by extending classes which
     * require the final XML package to be digested (and, perhaps, altered) in
     * a special way prior to transmission.
     *
     * These methods should take the full XML package as an argument and return
     * the new digested package. If the package is not altered by the digest it
     * must return the passed package unaltered.
     *
     * @param string $package The package to digest.
     * @return string The new (or unaltered) package after application of the digest.
     */
    protected function packageDigest($package)
    {
        return $package;
    }

    /**
     * Packages the message currently stored in the object into a valid GovTalk
     * envelope ready for sending.
     *
     * @return mixed The XML package (as a string) in GovTalk format, or false on failure.
     */
    protected function packageGovTalkEnvelope()
    {
        // Firstly check we have everything we need to build the envelope...
        $allSet = isset(
            $this->messageClass,
            $this->messageQualifier,
            $this->govTalkSenderId,
            $this->govTalkPassword,
            $this->messageAuthType
        );
        if (!$allSet) {
            $this->logError(
                'ENVELOPE_PROPERTIES_MISSING',
                'Essential information to build envelope missing',
                'GovTalk::packageGovTalkEnvelope',
            );
            return false;
        }

        if (!isset($this->messageBody)) {
            $this->logError(
                'MESSAGE_BODY_MISSING',
                'Message body missing',
                'GovTalk::packageGovTalkEnvelope',
            );
            return false;
        }

        $this->generateTransactionId();

        // Create the XML document (in memory)...
        $package = new XMLWriter();
        $package->openMemory();
        $package->setIndent(true);

        // Packaging...
        $package->startElement('GovTalkMessage');
        $xsiSchemaName = 'http://www.govtalk.gov.uk/CM/envelope';
        $xsiSchemaLocation = $xsiSchemaName.' http://www.govtalk.gov.uk/documents/envelope-v2-0.xsd';
        if ($this->additionalXsiSchemaLocation !== null) {
            $xsiSchemaLocation .= ' '.$this->additionalXsiSchemaLocation;
        }
        $package->writeAttribute('xmlns', $xsiSchemaName);
        $package->writeAttributeNS(
            'xsi',
            'schemaLocation',
            'http://www.w3.org/2001/XMLSchema-instance',
            $xsiSchemaLocation
        );
        $package->writeElement('EnvelopeVersion', '2.0');

        // Header...
        $package->startElement('Header');

        // Message details...
        $package->startElement('MessageDetails');
        $package->writeElement('Class', $this->messageClass);
        $package->writeElement('Qualifier', $this->messageQualifier);
        if ($this->messageFunction !== null) {
            $package->writeElement('Function', $this->messageFunction);
        }
        $package->writeElement('TransactionID', $this->transactionId);
        $package->writeElement('CorrelationID', $this->messageCorrelationId);
        $package->writeElement('Transformation', $this->messageTransformation);
        $package->writeElement('GatewayTest', $this->govTalkTest);

        /**
         * @see GovTalk::setTimestamp() for usage.
         */
        if ($this->timestamp && $this->govTalkTest === '1') {
            $package->writeElement('GatewayTimestamp', $this->timestamp->format('c'));
        }

        $package->endElement(); # MessageDetails

        // Sender details...
        $package->startElement('SenderDetails');

        // Authentication...
        $package->startElement('IDAuthentication');
        $package->writeElement('SenderID', $this->govTalkSenderId);
        $package->startElement('Authentication');
        switch ($this->messageAuthType) {
            case 'alternative':
                if ($authenticationArray = $this->generateAlternativeAuthentication($this->transactionId)) {
                    $package->writeElement('Method', $authenticationArray['method']);
                    $package->writeElement('Role', 'principal');
                    $package->writeElement('Value', $authenticationArray['token']);
                } else {
                    return false;
                }
                break;
            case 'clear':
                $package->writeElement('Method', 'clear');
                $package->writeElement('Role', 'principal');
                $package->writeElement('Value', $this->govTalkPassword);
                break;
            case 'MD5':
                $package->writeElement('Method', 'MD5');
                $package->writeElement('Value', base64_encode(md5(strtolower($this->govTalkPassword), true)));
                break;
        }
        $package->endElement(); # Authentication

        $package->endElement(); # IDAuthentication
        if ($this->senderEmailAddress !== null) {
            $package->writeElement('EmailAddress', $this->senderEmailAddress);
        }

        $package->endElement(); # SenderDetails

        $package->endElement(); # Header

        // GovTalk details...
        $package->startElement('GovTalkDetails');

        // Keys...
        if (count($this->govTalkKeys) > 0) {
            $package->startElement('Keys');
            foreach ($this->govTalkKeys as $keyPair) {
                $package->startElement('Key');
                $package->writeAttribute('Type', $keyPair['type']);
                $package->text($keyPair['value']);
                $package->endElement(); # Key
            }
            $package->endElement(); # Keys
        }

        // Target details...
        if (count($this->messageTargetDetails) > 0) {
            $package->startElement('TargetDetails');
            foreach ($this->messageTargetDetails as $targetOrganisation) {
                $package->writeElement('Organisation', $targetOrganisation);
            }
            $package->endElement(); # TargetDetails
        }

        // Channel routing...
        $channelRouteArray = $this->messageChannelRouting;
        if ($this->autoAppendOwnChannelRouting) {
            $channelRouteArray[] = [
                // This URI format is not valid for HMRC submissions, but `autoAppendOwnChannelRouting`
                // should be switched off for those anyway – so sticking with it for now in case it's helpful
                // for other govt departments.
                'uri' => 'https://abbpaysolutions.com/',
                'product' => 'abbpay-solutions',
                'version' => self::VERSION,
                'timestamp' => date('c')
            ];
        }
        foreach ($channelRouteArray as $channelRoute) {
            $package->startElement('ChannelRouting');
            $package->startElement('Channel');
            $package->writeElement('URI', $channelRoute['uri']);
            if (array_key_exists('product', $channelRoute)) {
                $package->writeElement('Product', $channelRoute['product']);
            }
            if (array_key_exists('version', $channelRoute)) {
                $package->writeElement('Version', $channelRoute['version']);
            }
            $package->endElement(); # Channel

            if (array_key_exists('id', $channelRoute) && is_array($channelRoute['id'])) {
                foreach ($channelRoute['id'] as $channelRouteId) {
                    $package->startElement('ID');
                    $package->writeAttribute('type', $channelRouteId['type']);
                    $package->text($channelRouteId['value']);
                    $package->endElement(); # ID
                }
            }

            $package->writeElement('Timestamp', $channelRoute['timestamp']);
            $package->endElement(); # ChannelRouting
        }
        $package->endElement(); # GovTalkDetails

        // Body...
        $package->startElement('Body');
        if (is_string($this->messageBody)) {
            $package->writeRaw("\n".trim($this->messageBody)."\n");
        } elseif ($this->messageBody instanceof \XMLWriter) {
            $package->writeRaw("\n".trim($this->messageBody->outputMemory())."\n");
        }
        $package->endElement(); # Body

        $package->endElement(); # GovTalkMessage

        // Flush the buffer, run any extension-specific digests, validate the schema
        // and return the XML...
        $xmlPackage = $this->packageDigest($package->flush());
        $validXMLRequest = true;
        if (isset($this->additionalXsiSchemaLocation) && ($this->schemaValidation == true)) {
            $validation = new DOMDocument();
            $validation->loadXML($xmlPackage);
            if (!$validation->schemaValidate($this->additionalXsiSchemaLocation)) {
                $validXMLRequest = false;
            }
        }
        if ($validXMLRequest === true) {
            return $xmlPackage;
        }

        return false;
    }

    /**
     * Packages the given array into an XMLWriter object where each element takes
     * its name from the array index, and its value from the array value.  In the
     * case of nested arrays each level is added below the previous element (as
     * you would expect).  Where an array has numeric indices each element takes
     * its name from the parent array.
     *
     * @param mixed $informationArray The information to be turned into an XMLWriter object.
     * @param string $parentElement The name of the parent element, if the $informationArray is numerically indexed.
     * @return XMLWriter An XMLWriter object representing the given array in XML.
     */
    protected function xmlPackageArray($informationArray, $parentElement = null)
    {
        if (is_array($informationArray)) {
            $package = new XMLWriter();
            $package->openMemory();
            $package->setIndent(true);

            foreach ($informationArray as $elementKey => $elementValue) {
                if (is_array($elementValue)) {
                    $packagedArray = $this->xmlPackageArray($elementValue, $elementKey);
                    reset($elementValue);
                    if (!is_int(key($elementValue))) {
                        $package->startElement($elementKey);
                        $package->writeRaw("\n".trim($packagedArray->outputMemory())."\n");
                        $package->endElement();
                    } else {
                        $package->writeRaw("\n".trim($packagedArray->outputMemory())."\n");
                    }
                } else {
                    if (is_int($elementKey)) {
                        $elementKey = $parentElement;
                    }
                    $package->writeElement($elementKey, $elementValue);
                }
            }
            return $package;
        }
        return false;
    }


    /* Private methods. */

    /**
     * Generates the transaction ID required for GovTalk authentication. Although
     * the GovTalk specifcation defines a valid transaction ID as [0-9A-F]{0,32}
     * some government gateways using GovTalk only accept numeric transaction
     * IDs. Therefore this implementation generates only a numeric transaction
     * ID.
     *
     * @return boolean Always returns true.
     */
    private function generateTransactionId()
    {
        list($usec, $sec) = explode(' ', microtime());
        $this->transactionId = $sec.str_replace('0.', '', $usec);
        return true;
    }

    /*
     * Sets up the default HTTP Client
     */
    private function getDefaultHttpClient(): Client
    {
        return new Client([
            'curl.options' => [
                CURLOPT_CONNECTTIMEOUT => 60,
                CURLOPT_RETURNTRANSFER => 1,
            ]
        ]);
    }

    /**
     * Capture libxml errors into internal array.
     */
    private function captureLibxmlErrors(): void
    {
        foreach (libxml_get_errors() as $error) {
            $this->schemaValidationErrors[] = [
                'level' => $error->level,
                'code' => $error->code,
                'message' => trim($error->message),
                'file' => $error->file,
                'line' => $error->line,
                'column' => $error->column,
            ];
        }
    }

    /**
     * Return captured schema validation errors.
     */
    public function getSchemaValidationErrors(): array
    {
        return $this->schemaValidationErrors;
    }

    /**
     * Reset schema validation errors.
     */
    public function clearSchemaValidationErrors(): void
    {
        $this->schemaValidationErrors = [];
    }
}
