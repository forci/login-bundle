# v0.2

- Renamed `Forci\Bundle\LoginBundle\Manager\LoginManager` to `Forci\Bundle\LoginBundle\Helper\LoginHelper`
- Changed service name from `forci_login.manager.%manager_name_from_config%` to `forci_login.helper.%manager_name_from_config%`
- Added `Forci\Bundle\LoginBundle\Helper\SilentLoginHelper`

# v0.3 

- Removed `remember_me` configuration option in favor of `rememberUser` and `rememberHWIOAuthAccessToken` methods. Having that functionality lead to many unexpected errors where developers would forget to pass in the request and it would throw an Exception, or otherwise fail silently, and "features" like that are not desired. Use the login method that suits you best instead.
