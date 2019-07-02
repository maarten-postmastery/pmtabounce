# Interspire bounce processing addon for PowerMTA

## Description

This Interspire addon is used to process bounces directly from PowerMTA accounting logs.

The addon provides the following advantages over the standard bounce processing in Interspire:

* No mailbox required to collect bounce mails
* No inefficient bounce mail parsing needed for local bounces
* Leverage PowerMTA remote bounce processor
* Categorization with PowerMTA bounce-category-patterns

Subscribers which hard bounce are disabled after one bounce. The following PowerMTA bounce categories are classified as hard bounce:

- bad-mailbox
- inactive-mailbox
- bad-domain

Subscribers which soft bounce are disabled after 5 soft bounces. This number is hard coded in Interspire. The following PowerMTA bounce categories are classified as soft bounce:

- quota-issues
- no-answer-from-host
- relaying-issues
- routing-errors

Bounce categories that are related to sender, delivery or reputation should have no consequence for subscribers. The following PowerMTA bounce categories are registered for statistics and ignored for subscribers:

- bad-connection
- content-related
- invalid-sender
- message-expired
- other
- policy-related
- protocol-errors
- spam-related

## Installation

### Configure PowerMTA

Create a directory for bounce logs and make sure PowerMTA can write to it:

    cd /var/log/pmta
    mkdir bounces
    chown pmta.pmta bounces

If the addon is configured to delete the logs after processing, it must have write permission.
    
Create a bounce log for processing by the addon.

    <acct-file /var/log/pmta/bounces.csv>
      move-to /var/log/pmta/bounces
      move-interval 5m
      records b, rb
      record-fields b timeLogged, rcpt, dsnAction, dsnStatus, dsnDiag, bounceCat, header_X-Mailer-LID, header_X-Mailer-SID
      record-fields rb timeLogged, rcpt, dsnAction, dsnStatus, dsnDiag, bounceCat, header_X-Mailer-LID, header_X-Mailer-SID
      world-readable yes
      iso-times no # unix timestamp
    </acct-file>
    
If admin/functions/api/ss_email.php was patched by Postmastery then headers are different and you need to use:

    record-fields b timeLogged, rcpt, dsnAction, dsnStatus, dsnDiag, bounceCat, header_X-List-ID, header_X-Mailing-ID
    record-fields rb timeLogged, rcpt, dsnAction, dsnStatus, dsnDiag, bounceCat, header_X-List-ID, header_X-Mailing-ID
    
### Install Addon

Copy pmtabounce addon directory to IEM\_HOME/admin/addons. IEM\_HOME should be something like /var/www/html/iem.

Make sure the pmtabounce directory is readable for user apache (CentOS) or www-data (Ubuntu).

### Register Addon

Select Settings -> Addons Settings.

Click the red cross in the Postmastery PowerMTA Bounce Addon line and the Installed? column

### Configure Addon

Click Configure.

* Specify the directory where bounce logs are moved to with move-to.
* Specify if bounce logs should be deleted or renamed (default).

### Configure Cron

Select Settings -> Cron Settings.

Disable standard Bounce Processing.

Enable Postmastery Bounce Processing. Set Run Every from Disable to the required interval for processing bounces from PowerMTA. The best is to match it with the move-interval in the PowerMTA configuration.

Press Save.

### Monitoring

The addon uses various status and error messages which can be viewed via Tools -> View Error Logs.

## License

Copyright (C) 2016-2019  Postmastery B.V.

This program is free software: you can redistribute it and/or modify it under the terms of the [GNU General Public License](https://choosealicense.com/licenses/gpl-3.0/) as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  If not, see <http://www.gnu.org/licenses/>.
