# WPVIP Woo Membership Addons

*Work in progress* 

A WordPress Plugin to provide workarounds for working with WooCommerce Membership exports on VIP Platform.

## Dependencies
* Woocommerce
* Woocommerce Memberships

### WP-CLI Command: Export Subscribers
This wp-cli command is used to export subscribers for a VIP WooCommerce membership with optional date parameters.

#### Command Syntax
wp vip-woo-membership exportsubscribers [--start_from_date=<start_from_date>] [--start_to_date=<start_to_date>] [--end_from_date=<end_from_date>] [--end_to_date=<end_to_date>]

#### Options
- --start_from_date=<start_from_date>: The start date for subscription exports to begin (YYYY-MM-DD format).
- --start_to_date=<start_to_date>: The start date for subscription exports to end (YYYY-MM-DD format).
- --end_from_date=<end_from_date>: The end date for subscription exports to begin (YYYY-MM-DD format).
- --end_to_date=<end_to_date>: The end date for subscription exports to end (YYYY-MM-DD format).
All options are optional. By default, the command will export all subscribers.

#### Examples
1. Export all subscribers:

```
wp vip-woo-membership exportsubscribers
```

2. Export subscribers with start dates between '2022-01-01' and '2022-01-31':

``` 
wp vip-woo-membership exportsubscribers --start_from_date=2022-01-01 --start_to_date=2022-01-31
```   

3. Export subscribers with end dates between '2022-02-01' and '2022-02-28':

```   
wp vip-woo-membership exportsubscribers --end_from_date=2022-02-01 --end_to_date=2022-02-28
```

4. Export subscribers with start dates between '2022-01-01' and '2022-01-31', and end dates between '2022-02-01' and '2022-02-28':

```   
wp vip-woo-membership exportsubscribers --start_from_date=2022-01-01 --start_to_date=2022-01-31 --end_from_date=2022-02-01 --end_to_date=2022-02-28
```
