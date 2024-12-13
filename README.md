# Nuvei Plugin for PrestaShop

## Description
Nuvei supports major international credit and debit cards enabling you to accept payments from your global customers.

A wide selection of region-specific payment methods can help your business grow in new markets. Other popular payment methods, from mobile payments to e-wallets, can be easily implemented on your checkout page.

The correct payment methods at the checkout page can bring you global reach, help you increase conversions, and create a seamless experience for your customers.

## System Requirements
- Prestashop v8.1.* and above  
- Working PHP cURL module

## Nuvei Requirements
- DMNs enabled in merchant settings.  
- Whitelisted plugin endpoint so the plugin can receive the DMNs.  
- On the Site ID level, "DMN timeout" setting should be not less than 20 seconds; 30 seconds is better.  
- If using the Rebilling plugin functionality, please provide the DMN endpoint to the Integration and Technical Support teams, so it can be added to the merchant configuration.


If you have an old PrestaShop version of the plugin, with Web SDK:

1. Create a backup of your site.
2. Complete all actions related to any orders for the current plugin before installing the plugin again.
3. Uninstall the current version and remove ROOT/modules/nuvei directory (if it is still there after the uninstall).

## Manual Installation
1. 1.	Download the last release of the plugin ("nuvei.ocmod.zip") in one of the following ways:
  - If you download the plugin from the main page (using the Code button):
    1. Extract the plugin and rename the folder to "nuvei_checkout".
	2. Add it to a ZIP archive.
  - If you downloaded the plugin from the Releases page continue.
2. Go to admin page **Modules > Modules & Services**.
3. Press the **Upload** button and upload the ZIP file.

## Support
Please contact our Technical Support (tech-support@nuvei.com) for any questions and difficulties.