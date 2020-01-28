<?php

namespace DansMaCulotte\Monetico\Payment;

use DansMaCulotte\Monetico\Exceptions\Exception;
use DansMaCulotte\Monetico\Exceptions\PaymentException;
use DansMaCulotte\Monetico\Resources\Authentication;
use DateTime;

class Response
{
  /** @var string */
  private $eptCode;

  /** @var string */
  private $fields;

  /** @var \DateTime */
  public $dateTime;

  /** @var string */
  public $amount;

  /** @var string */
  public $reference;

  /** @var string */
  public $seal;

  /** @var string */
  public $description;

  /** @var string */
  public $returnCode;

  /** @var string */
  public $cardVerificationStatus;

  /** @var string */
  public $cardExpirationDate;

  /** @var string */
  public $cardBrand;

  /** @var string */
  public $cardCountry;

  /** @var string */
  public $cardBIN;

  /** @var string */
  public $cardHash;

  /** @var bool */
  public $cardBookmarked = null;

  /** @var string */
  public $cardMask = null;

  /** @var int */
  public $DDDSStatus;

  /** @var string */
  public $rejectReason = null;

  /** @var string */
  public $authNumber;

  /** @var string */
  public $clientIp;

  /** @var string */
  public $transactionCountry;

  /** @var string */
  public $veresStatus;

  /** @var string */
  public $paresStatus;

  /** @var string */
  public $paymentMethod = null;

  /** @var string */
  public $commitmentAmount = null;

  /** @var int */
  public $filteredReason = null;

  /** @var string */
  public $filteredValue = null;

  /** @var string */
  public $filteredStatus = null;

  /** @var Authentication */
  public $authentication = null;

  /** @var string */
  public $authenticationHash = null;

  /** @var string */
  const DATETIME_FORMAT = 'd/m/Y_\a_H:i:s';

  /** @var array */
  const RETURN_CODES = [
    'payetest',
    'paiement',
    'Annulation',
    'paiement_pf2',
    'paiement_pf3',
    'paiement_pf4',
    'Annulation_pf2',
    'Annulation_pf3',
    'Annulation_pf4',
  ];

  /** @var array */
  const CARD_VERIFICATION_STATUSES = [
    'oui',
    'non',
  ];

  /** @var array */
  const CARD_BRANDS = [
    'AM' => 'American Express',
    'CB' => 'GIE CB',
    'MC' => 'Mastercard',
    'VI' => 'Visa',
    'na' => 'Non disponible',
  ];



  /** @var array  */
  const REJECT_REASONS = [
    'Appel Phonie',
    'Refus',
    'Interdit',
    'filtrage',
    'scoring',
    '3DSecure',
  ];

  /** @var array  */
  const PAYMENT_METHODS = [
    'CB',
    'paypal',
    '1euro',
    '3xcb',
    '4cb',
    'audiotel',
  ];

  /** @var array */
  const FILTERED_REASONS = [
    1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13, 14, 15, 16,
  ];

  /**
   * OutputPayload constructor.
   *
   * @param array $data
   *
   * @throws \Exception
   */
  public function __construct($data = [])
  {
    $this->validateRequiredKeys($data);

    $this->dateTime = DateTime::createFromFormat(self::DATETIME_FORMAT, $data['date']);
    if (!$this->dateTime instanceof DateTime) {
      throw Exception::invalidResponseDateTime();
    }

    $this->eptCode = $data['TPE'];
    $this->fields = $data;

    // ToDo: Split amount and currency with ISO4217
    $this->amount = $data['montant'];
    $this->reference = $data['reference'];
    $this->seal = $data['MAC'];
    $this->description = $data['texte-libre'];
    $this->authenticationHash = $data['authentification'];

    $this->returnCode = $data['code-retour'];
    if (!in_array($this->returnCode, self::RETURN_CODES)) {
      throw PaymentException::invalidResponseReturnCode($this->returnCode);
    }

    $this->cardVerificationStatus = $data['cvx'];
    if (!in_array($this->cardVerificationStatus, self::CARD_VERIFICATION_STATUSES)) {
      throw PaymentException::invalidResponseCardVerificationStatus($this->cardVerificationStatus);
    }

    $this->cardExpirationDate = $data['vld'];

    $this->cardBrand = $data['brand'];
    if (!in_array($this->cardBrand, array_keys(self::CARD_BRANDS))) {
      throw PaymentException::invalidResponseCardBrand($this->cardBrand);
    }

    // ToDo: Check Country
    $this->cardCountry = $data['originecb'];
    $this->authNumber = $data['numauto'];
    $this->cardBIN = $data['bincb'];
    $this->cardHash = $data['hpancb'];
    $this->clientIp = $data['ipclient'];

    // ToDo: Check Country
    $this->transactionCountry = $data['originetr'];

    $this->setAuthentication($data['authentification']);
    $this->setOptions($data);
    $this->setErrorsOptions($data);
  }


  /**
   * @param $data
   * @throws Exception
   */
  private function validateRequiredKeys($data)
  {
    $requiredKeys = [
      'TPE',
      'date',
      'montant',
      'reference',
      'MAC',
      'authentification',
      'texte-libre',
      'code-retour',
      'cvx',
      'vld',
      'brand',
      'numauto',
      'originecb',
      'bincb',
      'hpancb',
      'ipclient',
      'originetr',
    ];

    foreach ($requiredKeys as $key) {
      if (!in_array($key, array_keys($data))) {
        throw Exception::missingResponseKey($key);
      }
    }
  }

  /**
   * @param $authentication
   * @throws \DansMaCulotte\Monetico\Exceptions\AuthenticationException
   */
  private function setAuthentication($authentication)
  {
    $authentication = base64_decode($authentication);
    $authentication = json_decode($authentication);

    $this->authentication = new Authentication(
      $authentication->protocol,
      $authentication->status,
      $authentication->version,
      (isset($authentication->details)) ? (array) $authentication->details : []
    );
  }

  /**
   * @param $data
   * @throws PaymentException
   */
  private function setOptions($data)
  {
    if (isset($data['modepaiement'])) {
      $this->paymentMethod = $data['modepaiement'];
      if (!in_array($this->paymentMethod, self::PAYMENT_METHODS)) {
        throw PaymentException::invalidResponsePaymentMethod($this->paymentMethod);
      }
    }

    // ToDo: Split amount and currency with ISO4217
    if (isset($data['montantech'])) {
      $this->commitmentAmount = $data['montantech'];
    }

    if (isset($data['cbenregistree'])) {
      $this->cardBookmarked = (bool) $data['cbenregistree'];
    }

    if (isset($data['cbmasquee'])) {
      $this->cardMask = $data['cbmasquee'];
    }
  }

  /**
   * @param $data
   * @throws PaymentException
   */
  private function setErrorsOptions($data)
  {
    if (isset($data['filtragecause'])) {
      $this->filteredReason = (int) $data['filtragecause'];
      if (!in_array($this->filteredReason, self::FILTERED_REASONS)) {
        throw PaymentException::invalidResponseFilteredReason($this->filteredReason);
      }
    }

    if (isset($data['motifrefus'])) {
      $this->rejectReason = $data['motifrefus'];
      if (!in_array($this->rejectReason, self::REJECT_REASONS)) {
        throw PaymentException::invalidResponseRejectReason($this->rejectReason);
      }
    }

    if (isset($data['filtragevaleur'])) {
      $this->filteredValue = $data['filtragevaleur'];
    }

    if (isset($data['filtrage_etat'])) {
      $this->filteredStatus = $data['filtrage_etat'];
    }
  }

  private function fieldsToArray($eptCode)
  {
    $fields = [
      'TPE' => $eptCode,
      'authentification' => $this->authenticationHash,
      'bincb' => $this->cardBIN,
      'brand' => $this->cardBrand,
      'code-retour' => $this->returnCode,
      'cvx' => $this->cardVerificationStatus,
      'date' => $this->dateTime->format(self::DATETIME_FORMAT),
      'hpancb' => $this->cardHash,
      'ipclient' => $this->clientIp,
      'modepaiement' => $this->paymentMethod,
      'montant' => $this->amount,
      'numauto' => $this->authNumber,
      'originecb' => $this->cardCountry,
      'originetr' => $this->transactionCountry,
      'reference' => $this->reference,
      'texte-libre' => $this->description,
      'vld' => $this->cardExpirationDate,
    ];

    if (isset($this->rejectReason)) {
      $fields['motifrefus'] = $this->rejectReason;
    }


    if (isset($this->commitmentAmount)) {
      $fields['montantech'] = $this->commitmentAmount;
    }

    if (isset($this->filteredReason)) {
      $fields['filtragecause'] = $this->filteredReason;
    }

    if (isset($this->filteredValue)) {
      $fields['filtragevaleur'] = $this->filteredValue;
    }

    if (isset($this->filteredStatus)) {
      $fields['filtrage_etat'] = $this->filteredStatus;
    }

    if (isset($this->cardBookmarked)) {
      $fields['cbenregistree'] = $this->cardBookmarked;
    }

    if (isset($this->cardMask)) {
      $fields['cbmasquee'] = $this->cardMask;
    }

    return $fields;
  }

  /**
   * Validate seal to verify payment
   *
   * @param string $eptCode
   * @param string $securityKey
   * @param string $version
   *
   * @return bool
   */
  public function validateSeal($eptCode, $securityKey, $version)
  {
    $fields = $this->fields;
    unset($fields['MAC']);

    ksort($fields);

    $query = http_build_query($fields, null, '*');
    $query = urldecode($query);

    $hash = strtoupper(hash_hmac(
      'sha1',
      $query,
      $securityKey
    ));

    return $hash == $this->seal;
  }
}
