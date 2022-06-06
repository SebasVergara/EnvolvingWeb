
# -[Sendu](https://sendu-app.cl/) - Plugin for WooCommerce

This WooCommerce plugin allows any Chilean e-commerce to integrate Sendu as a courier service provider for their shipments.

The price of the shipment will be calculated according to the physical characteristics of the product such as its dimensions, the place of collection and place of delivery based on the user's postal code, returning the official rate and thus being able to quote in real time the value of the shipment of the products.

In case of not having coverage, the user is allowed to continue with the purchase, in order to get the store to quote the exact value of the shipment after the order.
## Demo

### To configure the plugin, you must have previously WooCommerce

![Sendu Backend](https://i.imgur.com/A06pIcK.png)

You can go to WooCommerce Shipping Settings -> Sendu, or for speed, there is a link in the backend called Sendu that will take you directly to settings.

There you must have a Sendu account, and fill in the fields correctly:

- Check if you want to be enabled
- **Title**: Title to be display on site
- **SendU URL**: Set the SendU API URL
- **SendU Email**: Set SendU email
- **Token SendU**: Set the SendU token
- **Status to generate order**: In what order status should a work order be generated
- **Alert dimensionless products**: What library will be used to notify alerts, warnings or errors
- **Action to take if there is no quote**: If you choose to hide shipping method, the SendU shipping method for that area is not shown, if you want to add the text "No-Coverage" the shipping will not be charged and you must add it manually when you have a quote or agree with your client.
- **Warning message**: Warning message on non coverage zone

# Enjoy your site connected with SendU and start selling!
## Authors

- [@SebasVergara](https://www.github.com/sebasvergara)


## Changelog

## [1.0.8] - 2020-09-24
### Fixed
- Show tracking info only if have data.

## [1.0.7] - 2020-09-23
### Added
- Add tracking info by order name.
- Shortcode added for tracking info.

## [1.0.6] - 2020-09-08
### Added
- Add bulk edit functionality to create work orders.
- Add button to get ID of work order if failed to get it initially.

## [1.0.5] - 2020-09-04
### Added
- Add single product quotation.

## [1.0.4] - 2020-08-29
### Added
- Default address form field are removed and added formatted form fields (Street, Numeration and Complement).
### Fixed
- Fixed data sending through API when work order is created (Categories, Client name and Formatted address).

## [1.0.3] - 2020-08-27
### Fixed
- Add Catch method to quotation when response are different to 200 OK.

## [1.0.2] - 2020-08-26
### Added
- Add custom label for alert when not quotation available.

## [1.0.1] - 2020-08-19
### Added
- Work Order for SendU are sent when administrator change order status to a previus defined status.

### Changed
- Backend configuration are modified to include more fields, and API access are modiafable.

### Fixed
- Quotation now include taxes.
## [1.0.0] - 2020-08-15
### Added
- Quotation by dimensions for SendU.
- Simple configuration page on Backend.
