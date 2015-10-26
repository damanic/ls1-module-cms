#Install
Overwrite modules/cms. Warning: adding this module as it is will prevent lemonstand from updating through the lemonstand V1 update center. This may not be an issue as support from lemonstand has ended.

##Google Analytics Integration Fix
Adds support for fetching google analytics report data with OAuth2.0

##Updates

- 1.20.0 Start community additions: Added $result_onBeforeDisplay to CMS_Controller
- 1.20.1|@1.20.1 Allows for GA tracking code to be used, separating it from the broken ClientLogin integration.
- 1.21.0|@1.21.0 Google Analytics OAUTH2 dashboard statistic integration.  Fixed issue with 1.20.1 where statistic integration toggle switch was also disabling some tracking codes. GA Tracking and Statistic Integration can be switched on/off independently.