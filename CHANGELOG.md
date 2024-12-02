# Nuvei Checkout plugin for Prestashop Changelog

# 2.1.1
```
    * Removed the version paramter from the main file. A getter method was created, who get the version from config.xml.
    * For the QA site add a specific parameter when call Simply Connect.
    * Fixed the check if the plugin is configured.
```

# 2.1.0
```
    * Nuvei rebilling fileds were added in a modified combination modal template.
    * Admin php javascripts were removed. Now only real javascript files are used.
    * The option to auto-close or not the APM popup was removed.
    * In the plugin settings, "Merchant Secret Key" field was masked.
    * Removed unused hooks.
```

# 2.0.1
```
    * Plugin was tested on Prestashop v8.2.0.
    * The maximum supported version was changed.
    * When a not approved DMN come, for Auth or Sale transaction, try only once to find the Order.
    * Added small margin under the plugin messages on Simply Connect page.
```

# 2.0.0
```
    * This version of the plugin was tested and works on Prestashop v8.1.*.
    * Fix the problem who prevents the merchant to add Nuvei Payment plan to the product in Prestasho v8.1.*.
    * Fix the problem where the client can combine ordinary product with Nuvei Rebilling product.
    * In case the plugin cancel to add a product to the Cart, and there is error, show first error message.
    * Removed old commened parts of code.
    * Removed a hook not working in Prestashop 8.1.* an up.
```

# 1.2.2
```
    * This version of the plugin was tested and works on Prestashop up to v8.0.*.
    * Added Tag SDK URL for test cases.
    * Add option to mask/unmask user details in the log.
    * Fixed typos in the plugin settings.
    * Fix for the sourceApplication parameter.
    * Pass sourceApplication to Simply Connect.
    * Formatted the readme.
```

# 1.2.1
```
    * Fix for the "SDK translations" setting example.
    * Added locale for Gpay button.
```

# 1.2.0
```
    Set different delay time in the DMN logic according the environment.
    Removed the updateOrder request on prePayment SDK event.
    Check for new version of the plugin every day and keep the repo version in the admin session.
    Fix for the module Reset exception.
    Added new table for Nuvei Order data. It will be used in future.
    Added an Order marker if the original amount/currency is different than the Prestshop amount/currency.
    Disable DCC when Order total is Zero.
```

# 1.1.0
```
    Fixed the problem with Rebilling activation DMN message in the admin. Prevent save of repeating messages.
    Added option to change SDK theme into the plugin.
    Added option to choose how to open APM window.
    Added new plugin logo.
    Added Auto-Void logic.
    Use new Sandbox ednpoint.
    Removed the logic who create Order, if missing in Prestashop system, by DMN data.
    Removed the plugins option "SDK version".
    Return code 400 to the Cashier, when the plugin did not find an OC Order nby the DMN data.
    Trim the merchant credentials after get them.
```
    
# 1.0.11
```
    Add sourceApplication parameter.
    Into webMasterId added Plugin version.
```

# 1.0.10
```
    Fix for the case when userTokenId can not be passed with updateOrder request.
    Removed few unused objects from the SDK call.
    When Void an Order check if there is active subscription before try to cancel it.
```

# 1.0.9
```
    Fix for the wrong Nuvei Documentation links into plugin settings.
    Show better message if the merchant currency is not supported by the APM.
```

# 1.0.8
```
    Fix for the missing insufficient funds message.
```

# 1.0.7
```
    Remove the Void button after 48 hours.
    Add "Cancel Subscription" button.
```

# 1.0.6
```
    Fix for the problem with the Rebiling with multiple products and wrong Recurring amount format.
    Fix for the recurring amount when used not default currency in the store.
    Added better formating for the money.
```

# 1.0.5
```
    Added new text message fror 'Insufficient funds' error.
    Unify products in a single rebilling plan, instead creating few Rebilings for an Order.
```

# 1.0.4
```
    Added correct links to Nuvei Documentation in the plugin settings.
```

# 1.0.3
```
    For the Checkout SDK pass billing address and into userData parameter.
```

# 1.0.2
```
    In OpenOrder request use bigger clientUniqueId parameter.
```

# 1.0.1
```
    Remove the repeating option in Advanced plugin settings "The Payment method text on the checkout". The proper option is in General tab - Default title.
    Move Nuvei Notes contaner 20px up.
```

# 1.0.0
```
    Based on Nuvei Web SDK plugin, but replace Web SDK with Checkout SDK.
```