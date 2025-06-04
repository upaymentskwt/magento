# UPayments - Magento

The Official Magento2 plugin for UPayments

---

## Installation

### Install using FTP method

*Note: Delete any previous UPayments plugin.*

1. Download the latest release of the plugin
2. Upload the content of the folder to magento2 installation directory: `app/code/Mageserv/UPayments`
3. Run the following Magento commands:
   1. `php bin/magento setup:upgrade`
   2. `php bin/magento setup:static-content:deploy`
   3. `php bin/magento cache:clean`

### Install using `Composer`

1. `composer require mageserv/upayments`
2. `php bin/magento setup:upgrade`
3. `php bin/magento setup:static-content:deploy`
4. `php bin/magento cache:clean`

---

## Activating the Plugin

By default and after installing the module, it will be activated.
To Disable/Enable the module:

### Enable

`php bin/magento module:enable Mageserv_UPayments`

### Disable

`php bin/magento module:disable Mageserv_UPayments`

---

## Configure the Plugin

1. Navigate to `"Magento admin panel" >> Stores >> Configuration`
2. Open `"Sales >> Payment Methods`
3. Select the preferred payment method from the available list of UPayments payment methods
4. Enable the `Payment Gateway`
5. Enter the primary credentials:
   - **Enable Live Mode:** Set to Yes after finish preprod testing
   - **Token: **Enter your API key
   - **Enable Debugging:** If set to "Yes", it will log all Upayments requests and save it to the log file. Please disable it after finishing your debug to save your disk space
6. Click `Save Config`

---

## Log Access

### UPayments log

1. Access log from file found at: `/var/log/system.log`

---

Done
