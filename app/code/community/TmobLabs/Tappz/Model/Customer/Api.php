<?php

class TmobLabs_Tappz_Model_Customer_Api extends Mage_Customer_Model_Api_Resource
{
    protected function _prepareCustomerData($data)
    {
        $genderAttributeCode = Mage::getStoreConfig('tappz/customer/gender');
        $emailAttributeCode = Mage::getStoreConfig('tappz/customer/email');
        $phoneAttributeCode = Mage::getStoreConfig('tappz/customer/phone');
        $birthDateAttributeCode = Mage::getStoreConfig('tappz/customer/birthDate');

        $result = array();
        $result['entity_id'] = $data->customerId;
        $result['firstname'] = $data->firstName;
        $result['lastname'] = $data->lastName;
        $result['password'] = $data->password;
        $result[$genderAttributeCode] = $data->gender;
        $result['isSubscribed'] = $data->isSubscribed;
        $result[$emailAttributeCode] = $data->email;
        $result[$phoneAttributeCode] = $data->phone;
        $result[$birthDateAttributeCode] = $data->birthDate;

        return $result;
    }

    public function info($customerId)
    {
        $genderAttributeCode = Mage::getStoreConfig('tappz/customer/gender');
        $emailAttributeCode = Mage::getStoreConfig('tappz/customer/email');
        $phoneAttributeCode = Mage::getStoreConfig('tappz/customer/phone');
        $birthDateAttributeCode = Mage::getStoreConfig('tappz/customer/birthDate');

        /* @var $customer Mage_Customer_Model_Customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if (!$customer) {
            $this->_fault("404.10", "Customer is not found.");
        }

        $result = array();
        $result['customerId'] = "";
        $result['fullName'] = "";
        $result['firstName'] = "";
        $result['lastName'] = "";
        $result['gender'] = "";
        $result['isSubscribed'] = false;
        $result['isAccepted'] = false;
        $result['email'] = "";
        $result['password'] = null;
        $result['phone'] = "";
        $result['birthDate'] = "";
        $result['points'] = 0;
        $result['addresses'] = array();
        $result['giftCheques'] = array();

        $result['customerId'] = $customer->getId();
        $result['fullName'] = $customer->getName();
        $result['firstName'] = $customer->getFirstname() . ($customer->getMiddleName() ? (' ' . $customer->getMiddleName()) : '');
        $result['lastName'] = $customer->getLastName();
        $result['gender'] = $customer->getData($genderAttributeCode);
        $result['isAccepted'] = !$customer->isConfirmationRequired();
        $result['email'] = $customer->getData($emailAttributeCode);
        $result['phone'] = $customer->getData($phoneAttributeCode);
        $result['birthDate'] = $customer->getData($birthDateAttributeCode);

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getData($emailAttributeCode));
        $result['isSubscribed'] = (bool)$subscriber->getId();

        $points = Mage::getModel('enterprise_reward/reward');
        if ($points) {
            $points = $points->setCustomer($customer)
                ->setWebsiteId(Mage::app()->getWebsite()->getId())
                ->loadByCustomer()
                ->getPointsBalance();
        }
        $result['points'] = $points;

        $result['addresses'] = Mage::getSingleton('tappz/customer_address_api')->getList($customer->getId()); /// ??????

        $result['giftCheques'] = array();
        // TODO: mcgoncu - giftCheque
        return $result;
    }

    public function login($userName, $password)
    {
        $storeId = Mage::getStoreConfig('tappz/general/store');
        /** @var Mage_Core_Model_Store $store */
        $store = Mage::getModel('core/store')->load($storeId);

        $customer = Mage::getModel('customer/customer')
            ->setStore($store);
        /* @var $customer  Mage_Customer_Model_Customer */

        try {
            $customer->authenticate($userName, $password);
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                    $this->_fault('invalid_data', "Email is not confirmed.");
                    break;
                case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                    $this->_fault('invalid_data', "Invalid email or password.");
                    break;
                default:
                    $this->_fault('invalid_data', $e->getMessage());
                    break;
            }
        }

        $customer = $customer->loadByEmail($userName);
        return $this->info($customer->getId());
    }

    public function facebookLogin($facebookAccessToken, $facebookUserId)
    {
        //TODO : mcgoncu - fblogin
    }

    public function register($tCustomerData)
    {
        $storeId = Mage::getStoreConfig('tappz/general/store');
        /** @var Mage_Core_Model_Store $store */
        $store = Mage::getModel('core/store')->load($storeId);

        $customerData = $this->_prepareCustomerData($tCustomerData);
        try {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer');
            $customer->setData($customerData)
                ->setPassword($customerData['password'])
                ->setStore($store)
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }

        return $this->info($customer->getId());
    }

    public function update($tCustomerData)
    {
        $genderAttributeCode = Mage::getStoreConfig('tappz/customer/gender');
        $emailAttributeCode = Mage::getStoreConfig('tappz/customer/email');
        $phoneAttributeCode = Mage::getStoreConfig('tappz/customer/phone');
        $birthDateAttributeCode = Mage::getStoreConfig('tappz/customer/birthDate');

        $customerData = $this->_prepareCustomerData($tCustomerData);
        $customer = Mage::getModel('customer/customer')->load($customerData['entity_id']);
        /* @var $customer Mage_Customer_Model_Customer */

        if (!$customer->getId()) {
            $this->_fault('not_exists');
        }

        try {
            $customer->setData('firstname', $customerData['firstname']);
            $customer->setData('lastname', $customerData['lastname']);
            $customer->setData($genderAttributeCode, $customerData[$genderAttributeCode]);
            $customer->setData($emailAttributeCode, $customerData[$emailAttributeCode]);
            $customer->setData($phoneAttributeCode, $customerData[$phoneAttributeCode]);
            $customer->setData($birthDateAttributeCode, $customerData[$birthDateAttributeCode]);

            if (isset($customerData['isSubscribed']))
                $customer->setIsSubscribed($customerData['isSubscribed'] === 'true' ? true : false);

            $customer->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }

        return $this->info($customer->getId());
    }

    public function lostPassword($email)
    {
        if (!isset($email) || trim($email) === '') {
            $this->_fault("invalid_data", "Please enter a valid email address.");
        }

        $storeId = Mage::getStoreConfig('tappz/general/store');
        /** @var Mage_Core_Model_Store $store */
        $store = Mage::getModel('core/store')->load($storeId);

        $customer = $customer = Mage::getModel('customer/customer')
            ->setStoreId($storeId)
            ->setWebsiteId($store->getWebsiteId())
            ->loadByEmail($email);

        if (!$customer) {
            $this->_fault("invalid_data", "Customer is not found");
        }
        $customer = $customer->sendPasswordReminderEmail();

        if (!$customer) {
            $this->_fault("invalid_data", "Error occured while sending email");
        }

        return "Your password reminder email has been sent.";
    }

    public function getUserAgreement()
    {
        $agreement = Mage::getStoreConfig('tappz/customer/agreement');
        return $agreement;
    }
}