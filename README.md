The NewRelic Monitor extends the capability of NewRelic alerts, allowing for custom 
alerts to be triggered for any trackable metric. Notifications are only sent when
there are one or more critical alerts.

You can find out which metrics can be tracked and the values you will want to configure
alerts for by creating a Custom View. Most graphs allow for warning and critical thresholds 
to be configured, and we highly recommend that you keep a Custom View maintained with 
the same set of graphs (or more) as are configured in `alerts.ini`.

More information: https://newrelic.com/docs/docs/custom-view-specification

Deployment
----------

 * Customise `alerts.example.ini` and save as `alerts.ini`
 * Setup `alerts.php` to be called via a cron-job or via Pingdom.
 * We recommend every 5-10 minutes.
 * When there are no alerts the script will output 'OK'.


Configuration File
------------------
The script looks for an ini file called `alerts.ini` which contains the alert conditions, NewRelic API settings and notification methods.


**Alerts:**

Alerts specify two thresholds and a time period that the value must be above the threshold to trigger an alert.

```
[alert:Name Of Alert]
app=1234                    ; NewRelic Application ID
metric=WebTransaction
field=requests_per_minute   
warn=5000                   ; Put into 'warning' mode when the value is above this
critical=10000              ; Critical state when value is above this
time=300                    ; Number of seconds that the value has to be above the threshold
```



**NewRelic Configuration:**

```
[newrelic]
account_id=1234
api_key=exampleapikey
```


**E-Mail Notifications:**

This will send an e-mail containing all the currently active alerts.
```
[notify:email]
from_email=alerts@example.com
from_name=NewRelic Alert Monitor
to_email=pagerduty@example.com
```