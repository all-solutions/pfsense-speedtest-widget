# Speedtest.net dashboard widget for pfSense [![GitHub release](https://img.shields.io/github/release/all-solutions/pfsense-speedtest-widget?include_prereleases=&sort=semver&color=blue)](https://github.com/all-solutions/pfsense-speedtest-widget/releases/latest)

**Forked from [Leon Straathof](https://github.com/LeonStraathof/pfsense-speedtest-widget) (repo doesn't seem to be maintained anymore)**

- added option to select interface for speed test ([@bastio84](https://github.com/bastio84/pfsense-speedtest-widget/tree/main))
- added fix breaking pfsense js ([@NetworkShark](https://github.com/NetworkShark/pfsense-speedtest-widget/tree/main))
- added filter to only show WAN interfaces in interface selection
- added additional VPN interfaces (tun/tap/ovpn) as speedtest sources<br>
![image](https://github.com/user-attachments/assets/79f5b910-6c3e-4ccd-a877-eacf88c87588)
- added easier way to download **speedtest.widget.php** via [Releases](https://github.com/all-solutions/pfsense-speedtest-widget/releases) (just need to klick on it for starting download)

___
This new widget is made to replace a similar widget created in the past by Alon Noy. That widget however used the not official speedtest-cli that is no longer supported. The no longer supported version of speedtest-cli has a limitation that it can only list and connect 10 geographic choosen test servers which are in most cases never the best server for your tests. And also the test results are not the best when compared with the original speedtest-cli from speedtest.net.

![Screenshot](https://github.com/all-solutions/pfsense-speedtest-widget/blob/main/Widget-screenshot.png?raw=true)

## INSTALL

- Go to https://www.speedtest.net/apps/cli

- Scroll down, click under "Install Options" on FreeBSD and find the URL of newest version<br>(for pfSense with FreeBSD 14 use the FreeBSD 13 version of cli)

- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command: <br>
(Use the URL found on the speedtest.net website and the FreeBSD version number in env ABI must match the version number in the URL)<br>
There is a know conflict between the not offical speedtest-cli and the offical version from speedtest.net. You can not have both installed at the same time. See this reported issue: https://github.com/LeonStraathof/pfsense-speedtest-widget/issues/2
```	
env ABI=FreeBSD:13:x86:64 pkg add "https://install.speedtest.net/app/cli/ookla-speedtest-1.2.0-freebsd13-x86_64.pkg"
```
- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command:
```
speedtest --accept-license
```
- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command:
```
speedtest --accept-gdpr
```
- Download latest [Release](https://github.com/all-solutions/pfsense-speedtest-widget/releases/latest) of the widget

- pfSense-main-menu-->Diagnotics-->Command Prompt-->Upload File:
speedtest.widget.php

- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command:
```
mv -f /tmp/speedtest.widget.php /usr/local/www/widgets/widgets/
```
- pfSense-main-menu:<br>
Click on "+" right from "Available Widgets" and add the speedtest widget.<br>
![image](https://github.com/user-attachments/assets/01a211f5-2813-4f16-9c7f-457c6b7bcccc)<br>
(don't forget to click "save" after that)
	
## UNINSTALL
It is enough to remove the widget from the dashboard. After that, neither the widget nor the speedtest-cli will be triggered anymore.<br>If you want to delete the associated files completely from pfSense:

- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command:
```
pkg info | grep speedtest
```
- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command:<br>
(use the package name found in the first step)	
```
pkg delete -y speedtest-1.2.0.84-1.ea6b6773cf
```
- pfSense-main-menu-->Status-->Dashboard:<br>
Remove the speedtest widget.

- pfSense-main-menu-->Diagnotics-->Command Prompt-->Execute Shell Command:
```
rm -f /usr/local/www/widgets/widgets/speedtest.widget.php
```


## TO-DO

- ~~Try to implement VPN interfaces as speedtest gateways~~


