<?php
require_once 'vendor/autoload.php';
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Filter\Customers\CustomerFilter;
use RetailCrm\Api\Model\Request\Customers\CustomersRequest;
use RetailCrm\Api\Model\Request\Customers\CustomersEditRequest;
use RetailCrm\Api\Exception\Client\BuilderException;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Entity\Customers\Customer;


$url = '';
$apiKey = '';

try {
    $client = SimpleClientFactory::createClient($url, $apiKey);
    $userSettings = $client->settings->get();
    $userSettingsTimeZone = $userSettings->settings->timezone->value;
} catch (BuilderException $e) {
    echo "BuilderException occurred: " . $e->getMessage();
    exit;
} catch (ApiExceptionInterface $e) {
    echo "Error occurred while fetching user settings: " . $e->getMessage();
    exit;
}

$userArray = [];
if (($handle = fopen("book1.csv", "r")) !== FALSE) {
    fseek($handle, 3);
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        $userArray[] = $data;
    }
    fclose($handle);
}

$processedEmails = [];

foreach ($userArray as $element) {
    foreach ($element as $email) {
        if (!empty($email)) {
            if (!in_array($email, $processedEmails)) {
                try {
                    $processedEmails[] = $email;

                    $userRequest = new CustomersRequest();
                    $userRequest->filter = new CustomerFilter();
                    $userRequest->filter->email = $email;
                    $userResponse = $client->customers->list($userRequest);

                    if (!empty($userResponse->customers)) {
                        $customer = $userResponse->customers[0];

                        if (empty($customer->emailMarketingUnsubscribedAt)) {
                            $editRequest = new CustomersEditRequest();
                            $editRequest->customer = new Customer();
                            $editRequest->by = ByIdentifier::ID;
                            $editRequest->site = $customer->site;
                            $emailUnsubscribeDate = new DateTime("now", new DateTimeZone($userSettingsTimeZone));
                            $editRequest->customer->emailMarketingUnsubscribedAt = $emailUnsubscribeDate;
                            $userResponse = $client->customers->edit($customer->id, $editRequest);
                            echo "Customer: " . $customer->id . " is successfully unsubscribed\n";
                        } else {
                            echo "Customer: " . $customer->id . " is already unsubscribed\n";
                        }
                    } else {
                        echo "Customer with email $email not found in CRM\n";
                    }
                } catch (ApiExceptionInterface|ClientExceptionInterface $exception) {
                    echo "Error processing customer with email $email: " . $exception->getMessage() . "\n";
                }
            } else {
                echo "Email $email has already been processed\n";
            }
        } else {
            echo "Empty email provided\n";
        }
    }
}


