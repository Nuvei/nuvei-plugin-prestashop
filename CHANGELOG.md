# Nuvei Checkout plugin for Prestashop Changelog

# 1.2.0
```
    Set different delay time in the DMN logic according the environment.
    Removed the updateOrder request on prePayment SDK event.
    Check for new version of the plugin every day and keep the repo version in the admin session.
    Fix for the module Reset exception.
    Added new table for Nuvei Order data. It will be used in future.
    Added an Order marker if the original amount/currency is different than the Prestshop amount/currency.
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