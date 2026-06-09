<?php

/**
 * Bank Account class
 */

namespace Omnipay\Stripe;

use DateTime;
use DateTimeZone;
use Omnipay\Stripe\Exception\InvalidBankAccountException;
use Omnipay\Common\ParametersTrait;

/**
 * Bank Account class
 *
 * Example:
 *
 * <code>
 *   // Define bank account parameters, which should look like this
 *   $parameters = [
 *       'firstName' => 'Bobby',
 *       'lastName' => 'Tables',
 *       'accountType' => 'checking'
 *       'routingNumber' => '122105155',
 *       'accountNumber' => '123456789',
 *       'billingCountry' => 'US',
 *   ];
 *
 *   // Create a bank account object
 *   $bank = new BankAccount($parameters);
 * </code>
 *
 * The full list of bank attributes that may be set via the parameter to
 * *new* is as follows:
 *
 * * title
 * * firstName
 * * lastName
 * * name
 * * company
 * * address1
 * * address2
 * * city
 * * postcode
 * * state
 * * country
 * * phone
 * * phoneExtension
 * * fax
 * * accountType
 * * accountHolderType
 * * routingNumber
 * * accountNumber
 * * bankName
 * * billingTitle
 * * billingName
 * * billingFirstName
 * * billingLastName
 * * billingCompany
 * * billingAddress1
 * * billingAddress2
 * * billingCity
 * * billingPostcode
 * * billingState
 * * billingCountry
 * * billingPhone
 * * billingFax
 * * shippingTitle
 * * shippingName
 * * shippingFirstName
 * * shippingLastName
 * * shippingCompany
 * * shippingAddress1
 * * shippingAddress2
 * * shippingCity
 * * shippingPostcode
 * * shippingState
 * * shippingCountry
 * * shippingPhone
 * * shippingFax
 * * email
 * * birthday
 * * gender
 *
 * If any unknown parameters are passed in, they will be ignored.  No error is thrown.
 */
class BankAccount
{
    use ParametersTrait;

    const ACCOUNT_TYPE_CHECKING = "checking";
    const ACCOUNT_TYPE_SAVINGS = "savings";
    const ACCOUNT_TYPE_FUTSU = "futsu";
    const ACCOUNT_TYPE_TOZA = "toza";

    const ACCOUNT_HOLDER_TYPE_INDIVIDUAL = 'individual';
    const ACCOUNT_HOLDER_TYPE_COMPANY = 'company';

    /**
     * The regular expression for validating routing numbers
     *
     * @link http://www.routingnumber.com
     */
    const ROUTING_NUMBER_REGEX = '/^(0\d|1[0-2]|2[1-9]|3[0-2]|6[1-9]|7[0-2]|80)\d{7}$/';

    /**
     * Create a new BankAcount object using the specified parameters
     *
     * @param array $parameters An array of parameters to set on the new object
     */
    public function __construct($parameters = null)
    {
        $this->initialize($parameters);
    }

    /**
     * All known/supported bank account types, for the given region.
     *
     * @return array
     */
    public function getSupportedAccountTypes()
    {
        if (in_array($this->getBillingCountry(), ['Japan', 'JP'], true)) {
            return [
                self::ACCOUNT_TYPE_FUTSU,
                self::ACCOUNT_TYPE_TOZA,
            ];
        }

        return [
            self::ACCOUNT_TYPE_CHECKING,
            self::ACCOUNT_TYPE_SAVINGS,
        ];
    }

    /**
     * Gets the supported account holder types.
     *
     * @return array
     */
    public function getSupportedAccountHolderTypes()
    {
        return [
            self::ACCOUNT_HOLDER_TYPE_INDIVIDUAL,
            self::ACCOUNT_HOLDER_TYPE_COMPANY,
        ];
    }

    /**
     * Validate this bank account. If the bank account is invalid, InvalidBankAccountException is thrown.
     *
     * @see \Omnipay\Common\ParametersTrait::validate()
     * @see \Omnipay\Common\CreditCard::validate()
     *
     * @return void
     * @throws InvalidBankAccountException
     */
    public function validate()
    {
        $requiredParameters = [
            'accountNumber' => 'bank account number',
            'billingCountry' => 'bank account country',
        ];

        foreach ($requiredParameters as $key => $val) {
            if (!$this->getParameter($key)) {
                throw new InvalidBankAccountException("The $val is required");
            }
        }

        if (!empty($this->getAccountType()) && !in_array($this->getAccountType(), $this->getSupportedAccountTypes(), true)) {
            throw new InvalidBankAccountException('The bank account type is not in the supported list');
        }

        if (
            !empty($this->getAccountHolderType())
            && !in_array($this->getAccountHolderType(), $this->getSupportedAccountHolderTypes(), true)
        ) {
            throw new InvalidBankAccountException('The bank account holder type is not in the supported list');
        }

        if ($this->getRoutingNumber() === null) {
            return;
        }

        if (!preg_match('/^\d{9}$/i', $this->getRoutingNumber())) {
            throw new InvalidBankAccountException('The bank routing number should have 9 digits');
        }

        if (
            !preg_match(self::ROUTING_NUMBER_REGEX, $this->getRoutingNumber())
            || !$this->validateChecksum($this->getRoutingNumber())
        ) {
            throw new InvalidBankAccountException('The bank routing number is invalid');
        }
    }

    /**
     * Validate a bank routing number according to the checksum algorithm.
     *
     * @link https://github.com/activemerchant/active_merchant/blob/master/lib/active_merchant/billing/check.rb
     * @param string $number The bank routing number to validate
     * @return bool True if the supplied bank routing number is valid
     */
    private function validateChecksum($number)
    {
        $split = array_chunk(str_split($number), 3);
        $function = static function ($chars) {
            return ($chars[0] * 3)
                + ($chars[1] * 7)
                + ($chars[2]);
        };

        return array_sum(array_map($function, $split)) % 10 === 0;
    }

    /**
     * Set Card Title.
     *
     * @param string $value
     * @return $this
     */
    public function setTitle($value)
    {
        $this->setBillingTitle($value)
            ->setShippingTitle($value);

        return $this;
    }

    /**
     * Get Bank Title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getBillingTitle();
    }

    /**
     * Get Bank First Name.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->getBillingFirstName();
    }

    /**
     * Set Bank First Name (Billing and Shipping).
     *
     * @param string $value
     * @return $this
     */
    public function setFirstName($value)
    {
        $this->setBillingFirstName($value)
            ->setShippingFirstName($value);

        return $this;
    }

    /**
     * Get Bank Last Name.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->getBillingLastName();
    }

    /**
     * Set Bank Last Name (Billing and Shipping).
     *
     * @param string $value
     * @return $this
     */
    public function setLastName($value)
    {
        $this->setBillingLastName($value)
            ->setShippingLastName($value);

        return $this;
    }

    /**
     * Get Bank Name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->getBillingName();
    }

    /**
     * Set Bank Name (Billing and Shipping).
     *
     * @param string $value
     * @return $this
     */
    public function setName($value)
    {
        $this->setBillingName($value)
            ->setShippingName($value);

        return $this;
    }

    /**
     * Get Bank Account Number.
     *
     * @return string
     */
    public function getAccountNumber()
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * Set Bank Account Number
     *
     * Non-numeric characters are stripped out of the account number, so
     * it's safe to pass in strings such as "4444-3333 2222 1111" etc.
     *
     * @param string $value
     * @return $this
     */
    public function setAccountNumber($value)
    {
        // strip non-numeric characters
        return $this->setParameter('accountNumber', preg_replace('/\D/', '', $value));
    }

    /**
     * Get the last 4 digits of the Bank Account Number.
     *
     * @return ?string
     */
    public function getAccountNumberLastFour()
    {
        return substr($this->getAccountNumber(), -4, 4) ?: null;
    }

    /**
     * Returns a masked Bank Account Number with only the last 4 chars visible
     *
     * @param string $mask Character to use in place of numbers
     * @return string
     */
    public function getAccountNumberMasked($mask = 'X')
    {
        $maskLength = max(strlen($this->getAccountNumber()) - 4, 0);

        return str_repeat($mask, $maskLength) . $this->getAccountNumberLastFour();
    }

    /**
     * Get Bank Routing Number.
     *
     * @return string
     */
    public function getRoutingNumber()
    {
        return $this->getParameter('routingNumber');
    }

    /**
     * Set Bank Routing Number
     *
     * Non-numeric characters are stripped out of the routing number, so
     * it's safe to pass in strings such as "4444-3333 2222 1111" etc.
     *
     * @param string $value
     * @return $this
     */
    public function setRoutingNumber($value)
    {
        // strip non-numeric characters
        return $this->setParameter('routingNumber', preg_replace('/\D/', '', $value));
    }

    /**
     * Get Bank Account Type
     *
     * @return string
     */
    public function getAccountType()
    {
        return $this->getParameter('accountType');
    }

    /**
     * Set Bank Account Type
     *
     * @param string $value
     * @return $this
     */
    public function setAccountType($value)
    {
        return $this->setParameter('accountType', $value);
    }

    /**
     * Gets the account holder type.
     *
     * @return string
     */
    public function getAccountHolderType()
    {
        return $this->getParameter('accountHolderType');
    }

    /**
     * Sets the account holder type.
     *
     * @param string $value
     * @return $this
     */
    public function setAccountHolderType($value)
    {
        return $this->setParameter('accountHolderType', $value);
    }

    /**
     * Get Bank Name
     *
     * @return string
     */
    public function getBankName()
    {
        return $this->getParameter('bankName');
    }

    /**
     * Set Bank Name
     *
     * @param string $value
     * @return $this
     */
    public function setBankName($value)
    {
        return $this->setParameter('bankName', $value);
    }

    /**
     * Get the card billing title.
     *
     * @return string
     */
    public function getBillingTitle()
    {
        return $this->getParameter('billingTitle');
    }

    /**
     * Sets the card billing title.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingTitle($value)
    {
        return $this->setParameter('billingTitle', $value);
    }

    /**
     * Get the card billing name.
     *
     * @return string
     */
    public function getBillingName()
    {
        return trim($this->getBillingFirstName() . ' ' . $this->getBillingLastName());
    }

    /**
     * Split the full name in the first and last name.
     *
     * @param string $fullName
     * @return array With first and last name
     */
    protected function listFirstLastName($fullName)
    {
        $names = explode(' ', $fullName, 2);

        return [
            $names[0],
            isset($names[1]) ? $names[1] : null
        ];
    }

    /**
     * Sets the card billing name.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingName($value)
    {
        $names = $this->listFirstLastName($value);

        $this->setBillingFirstName($names[0])
            ->setBillingLastName($names[1]);

        return $this;
    }

    /**
     * Get the first part of the card billing name.
     *
     * @return string
     */
    public function getBillingFirstName()
    {
        return $this->getParameter('billingFirstName');
    }

    /**
     * Sets the first part of the card billing name.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingFirstName($value)
    {
        return $this->setParameter('billingFirstName', $value);
    }

    /**
     * Get the last part of the card billing name.
     *
     * @return string
     */
    public function getBillingLastName()
    {
        return $this->getParameter('billingLastName');
    }

    /**
     * Sets the last part of the card billing name.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingLastName($value)
    {
        return $this->setParameter('billingLastName', $value);
    }

    /**
     * Get the billing company name.
     *
     * @return string
     */
    public function getBillingCompany()
    {
        return $this->getParameter('billingCompany');
    }

    /**
     * Sets the billing company name.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingCompany($value)
    {
        return $this->setParameter('billingCompany', $value);
    }

    /**
     * Get the billing address, line 1.
     *
     * @return string
     */
    public function getBillingAddress1()
    {
        return $this->getParameter('billingAddress1');
    }

    /**
     * Sets the billing address, line 1.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingAddress1($value)
    {
        return $this->setParameter('billingAddress1', $value);
    }

    /**
     * Get the billing address, line 2.
     *
     * @return string
     */
    public function getBillingAddress2()
    {
        return $this->getParameter('billingAddress2');
    }

    /**
     * Sets the billing address, line 2.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingAddress2($value)
    {
        return $this->setParameter('billingAddress2', $value);
    }

    /**
     * Get the billing city.
     *
     * @return string
     */
    public function getBillingCity()
    {
        return $this->getParameter('billingCity');
    }

    /**
     * Sets billing city.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingCity($value)
    {
        return $this->setParameter('billingCity', $value);
    }

    /**
     * Get the billing postcode.
     *
     * @return string
     */
    public function getBillingPostcode()
    {
        return $this->getParameter('billingPostcode');
    }

    /**
     * Sets the billing postcode.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingPostcode($value)
    {
        return $this->setParameter('billingPostcode', $value);
    }

    /**
     * Get the billing state.
     *
     * @return string
     */
    public function getBillingState()
    {
        return $this->getParameter('billingState');
    }

    /**
     * Sets the billing state.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingState($value)
    {
        return $this->setParameter('billingState', $value);
    }

    /**
     * Get the billing country name.
     *
     * @return string
     */
    public function getBillingCountry()
    {
        return $this->getParameter('billingCountry');
    }

    /**
     * Sets the billing country name.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingCountry($value)
    {
        return $this->setParameter('billingCountry', $value);
    }

    /**
     * Get the billing phone number.
     *
     * @return string
     */
    public function getBillingPhone()
    {
        return $this->getParameter('billingPhone');
    }

    /**
     * Sets the billing phone number.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingPhone($value)
    {
        return $this->setParameter('billingPhone', $value);
    }

    /**
     * Get the billing phone number extension.
     *
     * @return string
     */
    public function getBillingPhoneExtension()
    {
        return $this->getParameter('billingPhoneExtension');
    }

    /**
     * Sets the billing phone number extension.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingPhoneExtension($value)
    {
        return $this->setParameter('billingPhoneExtension', $value);
    }

    /**
     * Get the billing fax number.
     *
     * @return string
     */
    public function getBillingFax()
    {
        return $this->getParameter('billingFax');
    }

    /**
     * Sets the billing fax number.
     *
     * @param string $value
     * @return $this
     */
    public function setBillingFax($value)
    {
        return $this->setParameter('billingFax', $value);
    }

    /**
     * Get the title of the card shipping name.
     *
     * @return string
     */
    public function getShippingTitle()
    {
        return $this->getParameter('shippingTitle');
    }

    /**
     * Sets the title of the card shipping name.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingTitle($value)
    {
        return $this->setParameter('shippingTitle', $value);
    }

    /**
     * Get the card shipping name.
     *
     * @return string
     */
    public function getShippingName()
    {
        return trim($this->getShippingFirstName() . ' ' . $this->getShippingLastName());
    }

    /**
     * Sets the card shipping name.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingName($value)
    {
        $names = $this->listFirstLastName($value);

        $this->setShippingFirstName($names[0])
            ->setShippingLastName($names[1]);

        return $this;
    }

    /**
     * Get the first part of the card shipping name.
     *
     * @return string
     */
    public function getShippingFirstName()
    {
        return $this->getParameter('shippingFirstName');
    }

    /**
     * Sets the first part of the card shipping name.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingFirstName($value)
    {
        return $this->setParameter('shippingFirstName', $value);
    }

    /**
     * Get the last part of the card shipping name.
     *
     * @return string
     */
    public function getShippingLastName()
    {
        return $this->getParameter('shippingLastName');
    }

    /**
     * Sets the last part of the card shipping name.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingLastName($value)
    {
        return $this->setParameter('shippingLastName', $value);
    }

    /**
     * Get the shipping company name.
     *
     * @return string
     */
    public function getShippingCompany()
    {
        return $this->getParameter('shippingCompany');
    }

    /**
     * Sets the shipping company name.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingCompany($value)
    {
        return $this->setParameter('shippingCompany', $value);
    }

    /**
     * Get the shipping address, line 1.
     *
     * @return string
     */
    public function getShippingAddress1()
    {
        return $this->getParameter('shippingAddress1');
    }

    /**
     * Sets the shipping address, line 1.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingAddress1($value)
    {
        return $this->setParameter('shippingAddress1', $value);
    }

    /**
     * Get the shipping address, line 2.
     *
     * @return string
     */
    public function getShippingAddress2()
    {
        return $this->getParameter('shippingAddress2');
    }

    /**
     * Sets the shipping address, line 2.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingAddress2($value)
    {
        return $this->setParameter('shippingAddress2', $value);
    }

    /**
     * Get the shipping city.
     *
     * @return string
     */
    public function getShippingCity()
    {
        return $this->getParameter('shippingCity');
    }

    /**
     * Sets the shipping city.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingCity($value)
    {
        return $this->setParameter('shippingCity', $value);
    }

    /**
     * Get the shipping postcode.
     *
     * @return string
     */
    public function getShippingPostcode()
    {
        return $this->getParameter('shippingPostcode');
    }

    /**
     * Sets the shipping postcode.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingPostcode($value)
    {
        return $this->setParameter('shippingPostcode', $value);
    }

    /**
     * Get the shipping state.
     *
     * @return string
     */
    public function getShippingState()
    {
        return $this->getParameter('shippingState');
    }

    /**
     * Sets the shipping state.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingState($value)
    {
        return $this->setParameter('shippingState', $value);
    }

    /**
     * Get the shipping country.
     *
     * @return string
     */
    public function getShippingCountry()
    {
        return $this->getParameter('shippingCountry');
    }

    /**
     * Sets the shipping country.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingCountry($value)
    {
        return $this->setParameter('shippingCountry', $value);
    }

    /**
     * Get the shipping phone number.
     *
     * @return string
     */
    public function getShippingPhone()
    {
        return $this->getParameter('shippingPhone');
    }

    /**
     * Sets the shipping phone number.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingPhone($value)
    {
        return $this->setParameter('shippingPhone', $value);
    }

    /**
     * Get the shipping phone number extension.
     *
     * @return string
     */
    public function getShippingPhoneExtension()
    {
        return $this->getParameter('shippingPhoneExtension');
    }

    /**
     * Sets the shipping phone number extension.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingPhoneExtension($value)
    {
        return $this->setParameter('shippingPhoneExtension', $value);
    }

    /**
     * Get the shipping fax number.
     *
     * @return string
     */
    public function getShippingFax()
    {
        return $this->getParameter('shippingFax');
    }

    /**
     * Sets the shipping fax number.
     *
     * @param string $value
     * @return $this
     */
    public function setShippingFax($value)
    {
        return $this->setParameter('shippingFax', $value);
    }

    /**
     * Get the billing address, line 1.
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->getParameter('billingAddress1');
    }

    /**
     * Sets the billing and shipping address, line 1.
     *
     * @param string $value
     * @return $this
     */
    public function setAddress1($value)
    {
        $this->setParameter('billingAddress1', $value)
            ->setParameter('shippingAddress1', $value);

        return $this;
    }

    /**
     * Get the billing address, line 2.
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->getParameter('billingAddress2');
    }

    /**
     * Sets the billing and shipping address, line 2.
     *
     * @param string $value
     * @return $this
     */
    public function setAddress2($value)
    {
        $this->setParameter('billingAddress2', $value)
            ->setParameter('shippingAddress2', $value);

        return $this;
    }

    /**
     * Get the billing city.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->getParameter('billingCity');
    }

    /**
     * Sets the billing and shipping city.
     *
     * @param string $value
     * @return $this
     */
    public function setCity($value)
    {
        $this->setParameter('billingCity', $value)
            ->setParameter('shippingCity', $value);

        return $this;
    }

    /**
     * Get the billing postcode.
     *
     * @return string
     */
    public function getPostcode()
    {
        return $this->getParameter('billingPostcode');
    }

    /**
     * Sets the billing and shipping postcode.
     *
     * @param string $value
     * @return $this
     */
    public function setPostcode($value)
    {
        $this->setParameter('billingPostcode', $value)
            ->setParameter('shippingPostcode', $value);

        return $this;
    }

    /**
     * Get the billing state.
     *
     * @return string
     */
    public function getState()
    {
        return $this->getParameter('billingState');
    }

    /**
     * Sets the billing and shipping state.
     *
     * @param string $value
     * @return $this
     */
    public function setState($value)
    {
        $this->setParameter('billingState', $value)
            ->setParameter('shippingState', $value);

        return $this;
    }

    /**
     * Get the billing country.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->getParameter('billingCountry');
    }

    /**
     * Sets the billing and shipping country.
     *
     * @param string $value
     * @return $this
     */
    public function setCountry($value)
    {
        $this->setParameter('billingCountry', $value)
            ->setParameter('shippingCountry', $value);

        return $this;
    }

    /**
     * Get the billing phone number.
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->getParameter('billingPhone');
    }

    /**
     * Sets the billing and shipping phone number.
     *
     * @param string $value
     * @return $this
     */
    public function setPhone($value)
    {
        $this->setParameter('billingPhone', $value)
            ->setParameter('shippingPhone', $value);

        return $this;
    }

    /**
     * Get the billing phone number extension.
     *
     * @return string
     */
    public function getPhoneExtension()
    {
        return $this->getParameter('billingPhoneExtension');
    }

    /**
     * Sets the billing and shipping phone number extension.
     *
     * @param string $value
     * @return $this
     */
    public function setPhoneExtension($value)
    {
        $this->setParameter('billingPhoneExtension', $value)
            ->setParameter('shippingPhoneExtension', $value);

        return $this;
    }

    /**
     * Get the billing fax number..
     *
     * @return string
     */
    public function getFax()
    {
        return $this->getParameter('billingFax');
    }

    /**
     * Sets the billing and shipping fax number.
     *
     * @param string $value
     * @return $this
     */
    public function setFax($value)
    {
        $this->setParameter('billingFax', $value)
            ->setParameter('shippingFax', $value);

        return $this;
    }

    /**
     * Get the card billing company name.
     *
     * @return string
     */
    public function getCompany()
    {
        return $this->getParameter('billingCompany');
    }

    /**
     * Sets the billing and shipping company name.
     *
     * @param string $value
     * @return $this
     */
    public function setCompany($value)
    {
        $this->setParameter('billingCompany', $value)
            ->setParameter('shippingCompany', $value);

        return $this;
    }

    /**
     * Get the account holder's email address.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->getParameter('email');
    }

    /**
     * Sets the account holder's email address.
     *
     * @param string $value
     * @return $this
     */
    public function setEmail($value)
    {
        return $this->setParameter('email', $value);
    }

    /**
     * Get the account holder's birthday.
     *
     * @param string $format
     * @return ?string
     */
    public function getBirthday($format = 'Y-m-d')
    {
        $value = $this->getParameter('birthday');

        return $value ? $value->format($format) : null;
    }

    /**
     * Sets the account holder's birthday.
     *
     * @param string $value
     * @return $this
     */
    public function setBirthday($value)
    {
        $value = $value ? new DateTime($value, new DateTimeZone('UTC')) : null;

        return $this->setParameter('birthday', $value);
    }

    /**
     * Get the account holder's gender.
     *
     * @return string
     */
    public function getGender()
    {
        return $this->getParameter('gender');
    }

    /**
     * Sets the account holder's gender.
     *
     * @param string $value
     * @return $this
     */
    public function setGender($value)
    {
        return $this->setParameter('gender', $value);
    }
}
