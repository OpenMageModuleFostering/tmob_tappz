<?php

class TmobLabs_Tappz_Model_Basket_Api extends Mage_Api_Model_Resource_Abstract
{
    public function get($quoteId, $customerId = null)
    {
        $decimalDivider = Mage::getStoreConfig('tappz/general/decimalSeparator');
        $thousandDivider = Mage::getStoreConfig('tappz/general/groupSeparator');
        $store = Mage::getStoreConfig('tappz/general/store');

        $lineAverageDeliveryDaysAttributeCode = Mage::getStoreConfig('tappz/basket/averagedeliverydaysattributecode');
        $creditCardPaymentType = Mage::getStoreConfig('tappz/basket/creditcardpaymenttype');

        $basket = array();
        $basket['id'] = null;
        $basket['lines'] = array();
        $basket['currency'] = null;
        $basket['discounts'] = array();
        $basket['delivery']['shippingAddress'] = array();
        $basket['delivery']['billingAddress'] = array();
        $basket['delivery']['shippingMethod'] = array();
        $basket['delivery'] = array();
        $basket['payment'] = array();
        $basket['itemsPriceTotal'] = null;
        $basket['discountTotal'] = null;
        $basket['subTotal'] = null;
        $basket['beforeTaxTotal'] = null;
        $basket['taxTotal'] = null;
        $basket['shippingTotal'] = null;
        $basket['total'] = null;
        $basket['paymentOptions'] = array();
        $basket['shippingMethods'] = array();
        $basket['giftCheques'] = array();
        $basket['spentGiftChequeTotal'] = null;
        $basket['usedPoints'] = null;
        $basket['usedPointsAmount'] = null;
        $basket['rewardPoints'] = null;
        $basket['paymentFee'] = null;
        $basket['estimatedSupplyDate'] = null;
        $basket['isGiftWrappingEnabled'] = false;
        $basket['giftWrapping'] = null;

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store);

        if (isset($customerId)) {
            $quote = $quote->loadByCustomer($customerId);
        } elseif (isset($quoteId)) {
            $quote = $quote->load($quoteId);
        }

        if (is_null($quote->getId())) {
            try {
                if (isset($customerId) && $customerId !== '') {
                    $customer = Mage::getModel("customer/customer")->load($customerId);
                    $quote = $quote->setCustomer($customer);
                }
                $quote = $quote->save();
            } catch (Mage_Core_Exception $e) {
                $this->_fault('invalid_data', $e->getMessage());
            }
        }

        $catalogApi = Mage::getSingleton('tappz/Catalog_Api');
        $addressApi = Mage::getSingleton('tappz/Customer_Address_Api');

        $basket['id'] = $quote->getId();

        foreach ($quote->getAllVisibleItems() as $item) {
            $line = array();
            $line['productId'] = $item->getData('product_id');
            $line['product'] = $catalogApi->getProduct($item->getData('product_id'));
            $line['quantity'] = $item->getData('qty');
            $line['price'] = number_format($item->getData('price'), 2, $decimalDivider, $thousandDivider);
            $line['priceTotal'] = number_format($item->getData('row_total'), 2, $decimalDivider, $thousandDivider);
            $line['averageDeliveryDays'] = $item->getData($lineAverageDeliveryDaysAttributeCode);
            $line['variants'] = array();// $item->getData('');
            $basket['lines'][] = $line;
        }

        $basket['currency'] = Mage::app()->getStore($quote->getStoreId())->getCurrencyCode();

        $quoteBillingAddress = $quote->getBillingAddress();
        if ($quoteBillingAddress)
            $basket['delivery']['billingAddress'] = $addressApi->get($quoteBillingAddress->getData('customer_address_id'));

        $quoteShippingAddress = $quote->getShippingAddress();
        if ($quoteShippingAddress) {
            $basket['delivery']['shippingAddress'] = $addressApi->get($quoteShippingAddress->getData('customer_address_id'));

            $basket['delivery']['shippingMethod']['id'] = $quoteShippingAddress->getData('shipping_method');
            $basket['delivery']['shippingMethod']['displayName'] = $quoteShippingAddress->getData('shipping_description');
            $basket['delivery']['shippingMethod']['trackingAddress'] = null;
            $basket['delivery']['shippingMethod']['price'] = number_format($quoteShippingAddress->getData('shipping_incl_tax'), 2, $decimalDivider, $thousandDivider);
            $basket['delivery']['shippingMethod']['priceForYou'] = null;
            $basket['delivery']['shippingMethod']['shippingMethodType'] = $quoteShippingAddress->getData('shipping_method');
            $basket['delivery']['shippingMethod']['imageUrl'] = null;

            $basket['discountTotal'] = number_format($quoteShippingAddress->getData('discount_amount'), 2, $decimalDivider, $thousandDivider);
            $basket['shippingTotal'] = number_format($quoteShippingAddress->getData('shipping_incl_tax'), 2, $decimalDivider, $thousandDivider);
        } else {
            // TODO : set dummy address
        }

        $basket['itemsPriceTotal'] = number_format($quote->getData('base_subtotal'), 2, $decimalDivider, $thousandDivider);
        if (!isset($basket['discountTotal']))
            $basket['discountTotal'] = floatval($quote->getData('subtotal')) - floatval($quote->getData('subtotal_with_discount'));
        $basket['subTotal'] = number_format($quote->getData('subtotal'), 2, $decimalDivider, $thousandDivider);
        $basket['total'] = number_format($quote->getData('grand_total'), 2, $decimalDivider, $thousandDivider);

        $basket['discounts'][0]['displayName'] = null;
        $basket['discounts'][0]['discountTotal'] = $basket['discountTotal'];
        $basket['discounts'][0]['promoCode'] = $quote->getData('coupon_code');

        $payment = $quote->getPayment();
        if (isset($payment)) {
            $paymentData = array();
            $paymentData['cashOnDelivery'] = null;
            $paymentData['creditCard'] = null;
            $method = $payment->getData('method');
            if ($method == 'checkmo') {
                $paymentData['methodType'] = 'MoneyTransfer';
                $paymentData['type'] = $method;
                $paymentData['displayName'] = 'Check / Money Order';
                $paymentData['bankCode'] = null;
                $paymentData['installment'] = null;
                $paymentData['accountNumber'] = '123456'; // TODO
                $paymentData['branch'] = '321'; // TODO
                $paymentData['iban'] = 'TR12 3456 7890 1234 5678 9012 00';
            } else if ($method == $creditCardPaymentType) {
                $paymentData['methodType'] = 'CreditCard';
                $paymentData['type'] = $payment->getData('cc_type');
                $paymentData['displayName'] = 'Credit Card';
                $paymentData['bankCode'] = null;
                $paymentData['installment'] = null;
                $paymentData['accountNumber'] = '**** **** **** ' . $payment->getData('cc_last4');
                $paymentData['branch'] = null;
                $paymentData['iban'] = null;
                $paymentData['creditCard'] = array();
                $paymentData['creditCard']['owner'] = null;
                $paymentData['creditCard']['number'] = '**** **** **** ' . $payment->getData('cc_last4');
                $paymentData['creditCard']['month'] = null;
                $paymentData['creditCard']['year'] = null;
                $paymentData['creditCard']['cvv'] = null;
                $paymentData['creditCard']['type'] = $payment->getData('cc_type');
            } else if ($method == 'cashondelivery') {
                $paymentData['methodType'] = 'CashOnDelivery';
                $paymentData['type'] = $method;
                $paymentData['displayName'] = 'Cash on Delivery';
                $paymentData['bankCode'] = null;
                $paymentData['installment'] = null;
                $paymentData['accountNumber'] = null;
                $paymentData['branch'] = null;
                $paymentData['iban'] = null;
                $paymentData['cashOnDelivery'] = array();
                $paymentData['cashOnDelivery']['type'] = $method;
                $paymentData['cashOnDelivery']['displayName'] = 'Cash on Delivery';
                $paymentData['cashOnDelivery']['additionalFee'] = null;
                $paymentData['cashOnDelivery']['description'] = 'Cash on delivery description.'; // TODO
                $paymentData['cashOnDelivery']['isSMSVerification'] = false;
                $paymentData['cashOnDelivery']['SMSCode'] = null;
                $paymentData['cashOnDelivery']['PhoneNumber'] = null;
            } else if ($method == 'paypal_express') {
                $paymentData['methodType'] = 'PayPal';
                $paymentData['type'] = $method;
                $paymentData['displayName'] = 'PayPal';
                $paymentData['bankCode'] = null;
                $paymentData['installment'] = null;
                $paymentData['accountNumber'] = null;
                $paymentData['branch'] = null;
                $paymentData['iban'] = null;
            } else if ($method == 'stripe') { // apple_pay
                $paymentData['methodType'] = 'ApplePay';
                $paymentData['type'] = $method;
                $paymentData['displayName'] = 'Apple Pay';
                $paymentData['bankCode'] = null;
                $paymentData['installment'] = null;
                $paymentData['accountNumber'] = null;
                $paymentData['branch'] = null;
                $paymentData['iban'] = null;
            }
            $basket['payment'] = $paymentData;
        }

        $paymentOptions = array();
        $paymentOptions['paypal'] = null;
        $paymentOptions['creditCards'] = array();
        $paymentOptions['moneyTransfers'] = array();
        $paymentOptions['cashOnDelivery'] = null;

        $methods = Mage::helper('payment')->getStoreMethods($store, $quote);
        foreach ($methods as $method) {
            $code = $method->getCode();
            if ($code == $creditCardPaymentType) {
                try {
                    $paymentOptions['creditCards'] = $this->getTaksitSecenekleri($quote->getId());
                } catch (Exception $e) {
                    $paymentOptions['creditCards'] = array();
                }
                if (!isset($paymentOptions['creditCards'])) {
                    $paymentOptions['creditCards'] = array();
                    $paymentOptions['creditCards'][0]['image'] = null;
                    $paymentOptions['creditCards'][0]['displayName'] = 'Default Credit Card';
                    $paymentOptions['creditCards'][0]['type'] = $creditCardPaymentType;
                    $paymentOptions['creditCards'][0]['installmentNumber'] = 0;
                    $paymentOptions['creditCards'][0]['installments'] = array();
                }
            } elseif ($code == 'checkmo') {
                $paymentOptions['moneyTransfers'] = array();
                $paymentOptions['moneyTransfers'][0]['id'] = $code;
                $paymentOptions['moneyTransfers'][0]['displayName'] = $method->getTitle();
                $paymentOptions['moneyTransfers'][0]['code'] = $code;
                $paymentOptions['moneyTransfers'][0]['branch'] = ' ';
                $paymentOptions['moneyTransfers'][0]['accountNumber'] = ' ';
                $paymentOptions['moneyTransfers'][0]['iban'] = ' ';
                $paymentOptions['moneyTransfers'][0]['imageUrl'] = 'https://ec2-54-195-21-237.eu-west-1.compute.amazonaws.com/bitnami/images/corner-logo.png'; // TODO
            } elseif ($code == 'cashondelivery') {
                $paymentOptions['cashOnDelivery'] = array();
                $paymentOptions['cashOnDelivery']['type'] = null;
                $paymentOptions['cashOnDelivery']['displayName'] = null;
                $paymentOptions['cashOnDelivery']['additionalFee'] = null;
                $paymentOptions['cashOnDelivery']['description'] = null;
                $paymentOptions['cashOnDelivery']['isSMSVerification'] = false;
                $paymentOptions['cashOnDelivery']['SMSCode'] = null;
                $paymentOptions['cashOnDelivery']['PhoneNumber'] = null;

                $paymentOptions['cashOnDelivery']['type'] = $code;
                $paymentOptions['cashOnDelivery']['displayName'] = $method->getTitle();
                $paymentOptions['cashOnDelivery']['additionalFee'] = '0'; // TODO
                $paymentOptions['cashOnDelivery']['description'] = 'Cash on delivery description text'; // TODO
                $paymentOptions['cashOnDelivery']['isSMSVerification'] = false;
                $paymentOptions['cashOnDelivery']['SMSCode'] = null;
                $paymentOptions['cashOnDelivery']['PhoneNumber'] = null;
            } elseif ($code == 'paypal_express') {
                $paymentOptions['paypal'] = array();
                $paymentOptions['paypal']['clientId'] = null;
                $paymentOptions['paypal']['displayName'] = null;
                $paymentOptions['paypal']['isSandbox'] = 'true';

                $paymentOptions['paypal']['clientId'] = null;
                $paymentOptions['paypal']['displayName'] = $method->getTitle();
                $paymentOptions['paypal']['isSandbox'] = (bool)Mage::getStoreConfig('tappz/basket/paypalissandbox');
            }
        }
        $basket['paymentOptions'] = $paymentOptions;

        $shippingMethods = array();
        if (isset($quoteShippingAddress)) {
            try {
                $quoteShippingAddress->collectShippingRates()->save();
                $groupedRates = $quoteShippingAddress->getGroupedAllShippingRates();

                foreach ($groupedRates as $carrierCode => $rates) {
                    foreach ($rates as $rate) {
                        $rateItem = array();
                        $rateItem['id'] = $rate->getData('code');
                        $rateItem['displayName'] = $rate->getData('method_title');
                        $rateItem['trackingAddress'] = null;
                        $rateItem['price'] = number_format($rate->getData('price'), 2, $decimalDivider, $thousandDivider);
                        $rateItem['priceForYou'] = null;
                        $rateItem['shippingMethodType'] = $rate->getData('code');
                        $rateItem['imageUrl'] = null;

                        $shippingMethods[] = $rateItem;
                    }
                }
            } catch (Mage_Core_Exception $e) {

            }
        }
        $basket['shippingMethods'] = $shippingMethods;

        $giftCheques = array();
        if ($quote->getData('gift_cards')) {
            // TODO
        }
        $basket['giftCheques'] = $giftCheques;

        $basket['spentGiftChequeTotal'] = number_format($quote->getData('gift_cards_amount'), 2, $decimalDivider, $thousandDivider);
        $basket['usedPoints'] = null; //TODO
        $basket['usedPointsAmount'] = null; //TODO
        $basket['rewardPoints'] = null;//TODO
        $basket['paymentFee'] = null;//TODO
        $basket['estimatedSupplyDate'] = null; // $this->getSupplyDate($quote->getId());
        $basket['isGiftWrappingEnabled'] = true;
        $basket['giftWrapping'] = array();
        $basket['giftWrapping']['giftWrappingFee'] = '0'; // TODO
        $basket['giftWrapping']['maxCharackter'] = '200'; // TODO
        $basket['giftWrapping']['isSelected'] = false;
        $basket['giftWrapping']['message'] = '';
        if ($quote->getGiftMessageId() > 0) {
            $giftMessage = Mage::getSingleton('giftmessage/message')->load($quote->getGiftMessageId());
            $quote->setGiftMessage($giftMessage->getMessage());
            $basket['giftWrapping']['isSelected'] = true;
            $basket['giftWrapping']['message'] = $giftMessage->getMessage();
        }
        $basket['errors'] = $quote->getErrors();
        return $basket;
    }

    public function merge($anonymousQuoteId, $customerId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $anonymousQuote Mage_Sales_Model_Quote */
        $anonymousQuote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($anonymousQuoteId);

        $customer = Mage::getModel('customer/customer')->load($customerId);
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->loadByCustomer($customer);

        if (is_null($quote->getId())) {
            try {
                $quote = $quote->setStoreId($store)
                    ->setIsActive(false)
                    ->setIsMultiShipping(false)
                    ->setCustomer($customer)
                    ->save();
            } catch (Mage_Core_Exception $e) {
                $this->_fault('invalid_data', $e->getMessage());
            }
        }

        try {
            $quote = $quote->merge($anonymousQuote);
            $quote = $quote->collectTotals()->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }

        return $this->get($quote->getId());
    }

    public function updateItems($quoteId, $updateList)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        foreach ($updateList as $item) {
            $productId = $item->productId;
            $qty = $item->qty;

            /* @var $product Mage_Catalog_Model_Product */
            $product = Mage::getModel('catalog/product')->load($productId);
            $quoteItem = $quote->getItemByProduct($product);
            $request = new Varien_Object(array('qty' => $qty));
            if (!$quoteItem) {
                $quote->addProduct($product, $request);
            } elseif ($qty == 0) {
                $quote->removeItem($quoteItem->getId());
            } else {
                $quote->updateItem($quoteItem->getId(), $request);
            }
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        return $this->get($quote->getId());
    }

    public function setAddress($quoteId, $shippingAddressId, $billingAddressId, $shippingMethodId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        if (!is_null($billingAddressId)) {
            /* @var $address Mage_Customer_Model_Address */
            $customerBillingAddress = Mage::getModel('customer/address')
                ->load($billingAddressId);
            /* @var $billingAddress Mage_Sales_Model_Quote_Address */
            $billingAddress = Mage::getModel('sales/quote_address')
                ->importCustomerAddress($customerBillingAddress);
            $quote->setBillingAddress($billingAddress);
        }

        if (!is_null($shippingAddressId)) {
            /* @var $address Mage_Customer_Model_Address */
            $customerShippingAddress = Mage::getModel('customer/address')
                ->load($shippingAddressId);
            /* @var $shippingAddress Mage_Sales_Model_Quote_Address */
            $shippingAddress = Mage::getModel('sales/quote_address')
                ->importCustomerAddress($customerShippingAddress)
                ->implodeStreetAddress();
//            /* @var $customer Mage_Customer_Model_Customer */
//            $customer = Mage::getModel('customer/customer')->load($shippingAddress->getCustomerId());
            $quote->setShippingAddress($shippingAddress)
                ->getShippingAddress()
                ->setCollectShippingRates(true);
        }

        if (!is_null($shippingMethodId)) {
            $quoteShippingAddress = $quote->getShippingAddress();
            if (is_null($quoteShippingAddress->getId())) {
                $this->_fault("shipping_address_is_not_set");
            }

            $rate = $quoteShippingAddress->collectShippingRates()->getShippingRateByCode($shippingMethodId);
            if (!$rate) {
                $this->_fault('shipping_method_is_not_available');
            }

            try {
                $quote->getShippingAddress()->setShippingMethod($shippingMethodId);
            } catch (Mage_Core_Exception $e) {
                $this->_fault('shipping_method_is_not_set', $e->getMessage());
            }
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        return $this->get($quote->getId());
    }

    public function setGiftWrapping($quoteId, $isSelected, $message)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        if (!$isSelected) {
            $quote->setGiftMessageId(0);
        } else {
            $giftMessage['type'] = 'quote';
            $giftMessage['message'] = $message;
            $giftMessages = array($quoteId => $giftMessage);
            $request = new Mage_Core_Controller_Request_Http();
            $request->setParam("giftmessage", $giftMessages);

            Mage::dispatchEvent(
                'checkout_controller_onepage_save_shipping_method',
                array('request' => $request, 'quote' => $quote)
            );
        }
        return $this->get($quote->getId());
    }

    public function useDiscount($quoteId, $promoCode)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId)
            ->setCouponCode(strlen($promoCode) ? $promoCode : '')
            ->setTotalsCollectedFlag(false)
            ->collectTotals()
            ->save();

        if ($quote->getCouponCode() != $promoCode) {
            $this->_fault('invalid_data', 'Discount code is unavailable.');
        }

        return $this->get($quote->getId());
    }

    public function deleteDiscount($quoteId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId)
            ->setCouponCode('')
            ->setTotalsCollectedFlag(false)
            ->collectTotals()
            ->save();

        if (strlen($quote->getCouponCode()) > 0) {
            $this->_fault('invalid_data', 'Discount code cannot be deleted.');
        }

        return $this->get($quote->getId());
    }

    public function useGiftCheques($quoteId, $code)
    {
        $store = Mage::getStoreConfig('tappz/general/store');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        // TODO - set gift cheque to the quote for enterprise edition

        return $this->get($quote->getId());
    }

    public function deleteGiftCheques($quoteId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        // TODO - delete gift cheque from the quote for enterprise edition

        return $this->get($quote->getId());
    }

    public function useUserPoints($quoteId, $points)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        // TODO - use customer's reward points

        return $this->get($quote->getId());
    }

    public function selectPaymentMethod($quoteId, $payment)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        $creditCardPaymentType = Mage::getStoreConfig('tappz/basket/creditcardpaymenttype');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $paymentData = array();

        switch ($payment->methodType) {
            case "CreditCard":
                $creditCard = $payment->creditCard;
                $type = null;

                if(!is_null($creditCard)) {
                    switch ($creditCard->type) {
                        case '1':
                            $type = "VI";
                            break;
                        case '2':
                            $type = "MC";
                            break;
                        case '3':
                            $type = "AE";
                            break;
                        default:
                            $type = '';
                            break;
                    }
                    $paymentData['cc_type'] = $type;
                    $paymentData['cc_owner'] = $creditCard->owner;
                    $paymentData['cc_number'] = $creditCard->number;
                    $paymentData['cc_exp_month'] = $creditCard->month;
                    $paymentData['cc_exp_year'] = $creditCard->year;
                    $paymentData['cc_cid'] = $creditCard->cvv;
                } else {
                    return $this->get($quote->getId());
                }

                $paymentMethod = $creditCardPaymentType;
                break;
            case "CashOnDelivery":
                $paymentMethod = "cashondelivery";
                break;
            case "MoneyTransfer":
                $paymentMethod = "checkmo";
                break;
            case "PayPal":
                $paymentMethod = "paypal_express";
                break;
            case "ApplePay":
                $paymentMethod = "stripe";
                return $this->get($quote->getId());
                break;
        }

        $paymentData['method'] = $paymentMethod;
        $quote = $this->setPaymentData($quote, $paymentData);
        return $this->get($quote->getId());
    }

    protected function setPaymentData($quote, $paymentData)
    {
        /* @var $quote Mage_Sales_Model_Quote */
        if ($quote->isVirtual()) {
            // check if billing address is set
            if (is_null($quote->getBillingAddress()->getId())) {
                $this->_fault('invalid_data', 'billing_address_is_not_set');
            }
            $quote->getBillingAddress()->setPaymentMethod(
                isset($paymentData['method']) ? $paymentData['method'] : null
            );
        } else {
            // check if shipping address is set
            if (is_null($quote->getShippingAddress()->getId())) {
                $this->_fault('invalid_data', 'shipping_address_is_not_set');
            }
            $quote->getShippingAddress()->setPaymentMethod(
                isset($paymentData['method']) ? $paymentData['method'] : null
            );
        }

        if (!$quote->isVirtual() && $quote->getShippingAddress()) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        $total = $quote->getBaseSubtotal();
        $methods = Mage::helper('payment')->getStoreMethods($quote->getStoreId(), $quote);
        foreach ($methods as $method) {
            if ($method->getCode() == $paymentData['method']) {
                /** @var $method Mage_Payment_Model_Method_Abstract */
                if (!($this->_canUsePaymentMethod($method, $quote)
                    && ($total != 0
                        || $method->getCode() == 'free'
                        || ($quote->hasRecurringItems() && $method->canManageRecurringProfiles())))
                ) {
                    $this->_fault('invalid_data', "method_not_allowed");
                }
            }
        }

        try {
            $payment = $quote->getPayment();
            $payment->importData($paymentData);

            $quote = $quote->setIsActive(true)
                ->setTotalsCollectedFlag(false)
                ->collectTotals()
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }

        return $quote;
    }

    protected function _canUsePaymentMethod($method, $quote)
    {
        /* @var $quote Mage_Sales_Model_Quote */
        /** @var $method Mage_Payment_Model_Method_Abstract */
        if (!$method->canUseForCountry($quote->getBillingAddress()->getCountry())) {
            return false;
        }

        if (!$method->canUseForCurrency(Mage::app()->getStore($quote->getStoreId())->getBaseCurrencyCode())) {
            return false;
        }

        /**
         * Checking for min/max order total for assigned payment method
         */
        $total = $quote->getBaseGrandTotal();
        $minTotal = $method->getConfigData('min_order_total');
        $maxTotal = $method->getConfigData('max_order_total');

        if ((!empty($minTotal) && ($total < $minTotal)) || (!empty($maxTotal) && ($total > $maxTotal))) {
            return false;
        }

        return true;
    }

    protected function purchase($quote)
    {
        /** @var $quote Mage_Sales_Model_Quote */
        if ($quote->getIsMultiShipping()) {
            $this->_fault('invalid_data', 'invalid_checkout_type');
        }
        if ($quote->getCheckoutMethod() == Mage_Checkout_Model_Api_Resource_Customer::MODE_GUEST
            && !Mage::helper('checkout')->isAllowedGuestCheckout($quote, $quote->getStoreId())
        ) {
            $this->_fault('invalid_data', 'guest_checkout_is_not_enabled');
        }
        /** @var $customerResource Mage_Checkout_Model_Api_Resource_Customer */
        $customerResource = Mage::getModel("checkout/api_resource_customer");
        $isNewCustomer = $customerResource->prepareCustomerForQuote($quote);

        try {
            $quote->collectTotals();
            /** @var $service Mage_Sales_Model_Service_Quote */
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            if ($isNewCustomer) {
                try {
                    $customerResource->involveNewCustomer($quote);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $order = $service->getOrder();
            if ($order) {
                Mage::dispatchEvent('checkout_type_onepage_save_order_after',
                    array('order' => $order, 'quote' => $quote));

                try {
                    $order->queueNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            Mage::dispatchEvent(
                'checkout_submit_all_after',
                array('order' => $order, 'quote' => $quote)
            );
        } catch (Mage_Core_Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }
        $quote->setIsActive(false)->save();
        return $order->getIncrementId();
    }

    public function getContract($quoteId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $agreements = array();
        if (Mage::getStoreConfigFlag('checkout/options/enable_agreements')) {
            $agreementsCollection = Mage::getModel('checkout/agreement')->getCollection()
                ->addStoreFilter($store)
                ->addFieldToFilter('is_active', 1);

            foreach ($agreementsCollection as $_a) {
                /** @var $_a  Mage_Checkout_Model_Agreement */
                $agreements[] = $_a;
            }
        }

        $contract = array();
        $contract['termOfUse'] = $agreements[0]['content'];
        $contract['salesContact'] = $agreements[1]['content'];
        return $contract;
    }

    public function purchaseCreditCard($quoteId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $orderId = $this->purchase($quote);
        return Mage::getSingleton('tappz/Customer_Order_Api')->info($orderId);
    }

    public function purchaseMoneyOrder($quoteId, $moneyOrderType)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $orderId = $this->purchase($quote);
        return Mage::getSingleton('tappz/Customer_Order_Api')->info($orderId);
    }

    public function purchaseCashOnDelivery($quoteId, $cashOnDelivery)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $orderId = $this->purchase($quote);
        return Mage::getSingleton('tappz/Customer_Order_Api')->info($orderId);
    }

    public function createNewOrder($quote)
    {
        Mage::getSingleton('checkout/session')->replaceQuote($quote);
        /* @var $quote Mage_Sales_Model_Quote */
        /* @var $convert Mage_Sales_Model_Convert_Quote */
        /* @var $transaction Mage_Core_Model_Resource_Transaction */
        $convert = Mage::getModel('sales/convert_quote');
        $transaction = Mage::getModel('core/resource_transaction');

        $quote->setIsActive(false);

        if ($quote->getCustomerId()) {
            $transaction->addObject($quote->getCustomer());
        }
        //$quote->setTotalsCollectedFlag(true);
        $transaction->addObject($quote);
        $quote->reserveOrderId();

        if ($quote->isVirtual()) {
            $order = $convert->addressToOrder($quote->getBillingAddress());
        } else {
            $order = $convert->addressToOrder($quote->getShippingAddress());
        }
        $order->setBillingAddress($convert->addressToOrderAddress($quote->getBillingAddress()));
        if ($quote->getBillingAddress()->getCustomerAddress()) {
            $order->getBillingAddress()->setCustomerAddress($quote->getBillingAddress()->getCustomerAddress());
        }
        if (!$quote->isVirtual()) {
            $order->setShippingAddress($convert->addressToOrderAddress($quote->getShippingAddress()));
            if ($quote->getShippingAddress()->getCustomerAddress()) {
                $order->getShippingAddress()->setCustomerAddress($quote->getShippingAddress()->getCustomerAddress());
            }
        }

        $order->setPayment($convert->paymentToOrderPayment($quote->getPayment()));
        $order->getPayment()->setTransactionId($quote->getPayment()->getTransactionId());

        foreach ($quote->getAllItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            $orderItem = $convert->itemToOrderItem($item);
            if ($item->getParentItem()) {
                $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
            }
            $order->addItem($orderItem);
        }

        $order->setCanSendNewEmailFlag(false);
        $order->setQuoteId($quote->getId());
        $order->setExtOrderId($quote->getPayment()->getTransactionId());
        $transaction->addObject($order);
        $transaction->addCommitCallback(array($order, 'save'));

        try {
            $transaction->save();
            $quote->setIsActive(false)->save();
            Mage::dispatchEvent(
                'sales_model_service_quote_submit_success',
                array(
                    'order' => $order,
                    'quote' => $quote
                )
            );
        } catch (Exception $e) {
            //reset order ID's on exception, because order not saved
            $order->setId(null);
            /** @var $item Mage_Sales_Model_Order_Item */
            foreach ($order->getItemsCollection() as $item) {
                $item->setOrderId(null);
                $item->setItemId(null);
            }

            Mage::dispatchEvent(
                'sales_model_service_quote_submit_failure',
                array(
                    'order' => $order,
                    'quote' => $quote
                )
            );
            $quote->setIsActive(true);
            $this->_fault('invalid_data', $e->getMessage());
        }
        Mage::dispatchEvent('checkout_submit_all_after', array('order' => $order, 'quote' => $quote));
        Mage::dispatchEvent('sales_model_service_quote_submit_after', array('order' => $order, 'quote' => $quote));

        return $order;
    }

    public function purchaseWithPayPal($quoteId, $transactionId)
    {
        $paypalIsSandBox = (bool)Mage::getStoreConfig('tappz/basket/paypalissandbox');
        $paypalClientId = Mage::getStoreConfig('tappz/basket/paypalclientid');
        $paypalSecret = Mage::getStoreConfig('tappz/basket/paypalSecret');

        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $url = $paypalIsSandBox
            ? "https://api.sandbox.paypal.com/v1/"
            : "https://api.paypal.com/v1/";

        $payment_result = array();
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . 'oauth2/token');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Accept-Language: en_US',
                'content-type: application/x-www-form-urlencoded'
            ));
            curl_setopt($ch, CURLOPT_USERPWD, $paypalClientId . ':' . $paypalSecret);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
            $token_result_json = curl_exec($ch);
            curl_close($ch);
            $token_result = json_decode($token_result_json);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . 'payments/payment/' . $transactionId);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token_result->access_token,
                'content-type: application/x-www-form-urlencoded'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $payment_result_json = curl_exec($ch);
            curl_close($ch);
            $payment_result = json_decode($payment_result_json, true);
        } catch (Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }

        $transactionState = $payment_result['state'];
        $saleState = $payment_result['transactions'][0]['related_resources'][0]['sale']['state'];

        if ($transactionState == 'approved' && $saleState == 'completed') {
            $quote->getPayment()->setTransactionId($transactionId)->save();
            $order = $this->createNewOrder($quote);
            return Mage::getSingleton('tappz/Customer_Order_Api')->info($order->getIncrementId());
        } else {
            $this->_fault('invalid_data', 'PayPal transaction is not completed.');
        }
    }

    public function purchaseWithApplePay($quoteId, $tokenId)
    {
        $stripeIsTest = (bool)Mage::getStoreConfig('tappz/basket/stripeistest');
        $stripeTestSecretKey = Mage::getStoreConfig('tappz/basket/stripetestsecret');
        $stripeLiveSecretKey = Mage::getStoreConfig('tappz/basket/stripelivesecret');

        $store = Mage::getStoreConfig('tappz/general/store');
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $secretKey = $stripeIsTest ? $stripeTestSecretKey : $stripeLiveSecretKey;

        $fields = array(
            'amount' => $quote->getGrandTotal(),
            'currency' => $quote->getQuoteCurrencyCode(),
            'source' => $tokenId,
            'description' => 't-appz Apple Pay',
        );

        $field_string = http_build_query($fields);
        $charge = array();
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/charges');
            curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            $charge_json = curl_exec($ch);
            curl_close($ch);
            $charge = json_decode($charge_json);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/charges/' . $charge->id . '/capture');
            curl_setopt($ch, CURLOPT_USERPWD, $secretKey);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $field_string);
            $charge_json = curl_exec($ch);
            curl_close($ch);
            $charge = json_decode($charge_json, true);
        } catch (Exception $e) {
            $this->_fault('invalid_data', $e->getMessage());
        }

        // TODO : test

        if ($charge['captured']) {
            $paymentData = array();
            $paymentData['method'] = 'ApplePay';
            $quote = $this->setPaymentData($quote, $paymentData);
            $quote->getPayment()->setTransactionId($tokenId)->save();
            $orderId = $this->createNewOrder($quote);
            return Mage::getSingleton('tappz/Customer_Order_Api')->info($orderId);
        } else {
            $this->_fault('invalid_data', 'Apple Pay transaction is not completed.');
        }
    }

    // TODO : mcgoncu - annelutfen icin ekle
    public function getTaksitSecenekleri($quoteId)
    {
        $bankInfos = array();

        $exclude_cats = array(219, 218, 527, 217, 651, 329, 331, 678);
        $show_taksits = true;
        $cats = array();
        $cart = Mage::getModel("sales/quote")->load($quoteId);
        $cartItems = $cart->getItems();
        $tutar = $cart->getGrandTotal();

        foreach ($cartItems as $item) {
            $cats[] = $item->getProduct()->getCategoryIds();
        }
        if (count($cats) > 0) {
            foreach ($cats as $vals) {
                foreach ($vals as $dd) {
                    if (in_array($dd, $exclude_cats)) {
                        $show_taksits = false;
                    }
                }
            }
        }

        $turkpay = Mage::getModel('Grinet_Turkpay_Model_Grinet');
        if ($turkpay) {
            $bank_avs = $turkpay->bankalar();
        }
        if (!empty($bank_avs)) {
            $bankCount = 0;
            $fark = 0;
            foreach ($bank_avs as $bank_code => $bank) {
                //$codes[] = $bank_code;
                // $bankName[] = $bank['name'];
                //  $tutar = 200;
                $tdata = $turkpay->taksitler($bank_code, $tutar);
                $valCount = 0;
                foreach ($tdata as $tay => $toran) {
                    /* price calculater */
                    $toplam_tutar = $tutar + (($tutar / 100) * floatval($toran)) + $fark;// - ( ( $fark / 100 ) * floatval($toran) ) ;
                    /* price calculater */
                    $custom_title = Mage::getStoreConfig($bank_code . '/taksit_baslik/taksit_' . $tay);
                    if (trim($custom_title) == '')
                        $taksit_title = 'Tek Çekim';
                    else
                        $taksit_title = $custom_title;

                    if ($show_taksits === false) {
                        $custom_title = Mage::getStoreConfig($bank_code . '/taksit_baslik/taksit_0');
                        if (trim($custom_title) == '')
                            $taksit_title = 'Tek Çekim';
                        else
                            $taksit_title = $custom_title;
                    }
                    $bankInfos[$bankCount]['image'] = null; // TODO : annelutfen - banka imajı
                    $bankInfos[$bankCount]['displayName'] = trim($bank['name']);
                    $bankInfos[$bankCount]['type'] = null;// TODO : annelutfen - kart kodu
                    $bankInfos[$bankCount]['installmentNumber'] = null; // TODO : annelutfen - toplanm taksit sayısı
                    $bankInfos[$bankCount]['installments'][$valCount]['installmentNumber'] = $valCount; // TODO - annelutfen - dogru mu bu?
                    $bankInfos[$bankCount]['installments'][$valCount]['installmentPayment'] = round($toplam_tutar / $valCount, 2, PHP_ROUND_HALF_UP); // TODO - annelutfen - dogru mu bu?
                    $bankInfos[$bankCount]['installments'][$valCount]['total'] = round($toplam_tutar, 2, PHP_ROUND_HALF_UP);
                    if ($show_taksits === false) {
                        if ($taksit_title == "Tek Çekim")
                            break;
                    }

                    $valCount++;
                }
                $bankCount++;
            }
        } else
            $bankInfos = null;
        return $bankInfos;
    }

    public function getSupplyDate($quoteId)
    {
        $store = Mage::getStoreConfig('tappz/general/store');
        $productAverageDeliveryDaysAttributeCode = Mage::getStoreConfig('tappz/basket/averagedeliverydaysattributecode');

        /* @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel("sales/quote")
            ->setStoreId($store)
            ->load($quoteId);

        $set_message = array();
        foreach ($quote->getAllItems() as $item) {
            $product = Mage::getModel('catalog/product')->load($item->getData('product_id'));
            /* @var $product Mage_Catalog_Model_Product */
            $message = $product->getData($productAverageDeliveryDaysAttributeCode);
            if ($message)
                $set_message[] = $message;
        }

        $set_message_unique = array_unique($set_message);

        $by_0 = false;
        $by_3 = false;
        $by_4 = false;
        $by_5 = false;
        $by_7 = false;
        if (in_array('Aynı Gün Kargoya Teslim', $set_message_unique)) {
            $by_0 = true;
        }
        if (in_array('1-3 İş Gününde Kargoya Teslim', $set_message_unique)) {
            $by_3 = true;
            $by_0 = false;
        }
        if (in_array('4-5 İş Gününde Kargoya Teslim', $set_message_unique)) {
            $by_4 = true;
            $by_3 = false;
            $by_0 = false;
        }
        if (in_array('5 İş Gününde Kargoya Teslim', $set_message_unique)) {
            $by_5 = true;
            $by_3 = false;
            $by_0 = false;
        }
        if (in_array('7 İş Gününde Kargoya Teslim', $set_message_unique)) {
            $by_7 = true;
            $by_5 = false;
            $by_3 = false;
            $by_0 = false;
        }

        $cMsg = '';
        if ($by_0) {
            $cMsg = "Sepetinizdeki ürünlerin tamamı aynı günde kargoya teslim edilecektir.";
        }
        if ($by_3) {
            $cMsg = "Sepetinizdeki ürünlerin tamamı 1-3 iş gününde kargoya teslim edilecektir.";
        }
        if ($by_4) {
            $cMsg = "Sepetinizdeki ürünlerin tamamı 4-5 iş gününde kargoya teslim edilecektir.";
        }
        if ($by_5) {
            $cMsg = "Sepetinizdeki ürünlerin tamamı 5 iş gününde kargoya teslim edilecektir.";
        }
        if ($by_7) {
            $cMsg = "Sepetinizdeki ürünlerin tamamı 7 iş gününde kargoya teslim edilecektir.";
        }

        return $cMsg;
    }
}