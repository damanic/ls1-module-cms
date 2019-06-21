# CMS Module

### Lemonstand Version 1
This updated module can be installed using the updatecenter module: https://github.com/damanic/ls1-module-updatecenter

#### Google Analytics Integration Fix
Adds support for fetching google analytics report data with OAuth2.0

#### Updates

- 1.20.0 Start community additions: Added $result_onBeforeDisplay to CMS_Controller
- 1.20.1 Allows for GA tracking code to be used, separating it from the broken ClientLogin integration.
- 1.21.0 Google Analytics OAUTH2 dashboard statistic integration.  Fixed issue with 1.20.1 where statistic integration toggle switch was also disabling some tracking codes. GA Tracking and Statistic Integration can be switched on/off independently.
- 1.21.1 Minor improvement to GA authentication: cache file uses lemonstand temp directory.
- 1.21.2 Upgraded GA tracking to Universal Analytics, analytics.js library
- 1.21.3 Fixed syntax error on GA ecommerce:addTransaction
- 1.21.4 Added event cms:onBeforeSaveContentBlock
- 1.21.5 GA stats fix. Replaced deprecated ga:timeOnSite metric with ga:avgSessionDuration
- 1.21.6 Support IPV6
- 1.21.7 Improves login redirects