<?php namespace pineapple;

class Networking extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'getRoutingTable':
                $this->getRoutingTable();
                break;

            case 'restartDNS':
                $this->restartDNS();
                break;

            case 'updateRoute':
                $this->updateRoute();
                break;

            case 'getAdvancedData':
                $this->getAdvancedData();
                break;

            case 'setHostname':
                $this->setHostname();
                break;

            case 'resetWirelessConfig':
                $this->resetWirelessConfig();
                break;

            case 'getInterfaceList':
                $this->getInterfaceList();
                break;

            case 'saveAPConfig':
                $this->saveAPConfig();
                break;

            case 'getAPConfig':
                $this->getAPConfig();
                break;

            case 'getMacData':
                $this->getMacData();
                break;

            case 'setMac':
                $this->setMac(false);
                break;

            case 'setRandomMac':
                $this->setMac(true);
                break;

            case 'resetMac':
                $this->resetMac();
                break;

            case 'scanForNetworks':
                $this->scanForNetworks();
                break;

            case 'getClientInterfaces':
                $this->getClientInterfaces();
                break;

            case 'connectToAP':
                $this->connectToAP();
                break;

            case 'checkConnection':
                $this->checkConnection();
                break;

            case 'disconnect':
                $this->disconnect();
                break;
        }
    }

    private function checkConnection()
    {
        $connection = exec('iwconfig 2>&1 | grep ESSID:\"');
        if (trim($connection)) {
            $interface = explode(" ", $connection)[0];
            
            $ssidString = substr($connection, strpos($connection, 'ESSID:'));
            $ssid = substr($ssidString, 7, -1);
            $ip = exec("ifconfig " . escapeshellarg($interface) . " | grep inet | awk '{print \$2}' | awk -F':' '{print \$2}'");
            $this->response = array("connected" => true, "interface" => $interface, "ssid" => $ssid, "ip" => $ip);
        } else {
            $this->response = array("connected" => false);
        }
    }

    private function disconnect()
    {
        $uciID = $this->getUciID($this->request->interface);

        $this->uciSet("wireless.@wifi-iface[{$uciID}].network", 'lan');
        $this->uciSet("wireless.@wifi-iface[{$uciID}].ssid", '');
        $this->uciSet("wireless.@wifi-iface[{$uciID}].encryption", 'none');
        $this->uciSet("wireless.@wifi-iface[{$uciID}].key", '');

        $radioID = $this->getRadioID($this->request->interface);
        if ($radioID === false) {
            $this->execBackground("wifi");
        } else {
            $this->execBackground("wifi reload {$radioID}");
            $this->execBackground("wifi up {$radioID}");
        }

        $this->response = array("success" => true);
    }

    private function getClientInterfaces()
    {
        $this->response = array();
        exec("ifconfig -a | grep wlan | awk '{print \$1}'", $interfaceArray);

        foreach ($interfaceArray as $interface) {
            if (substr($interface, 0, 5) === "wlan0") {
                continue;
            }
            array_push($this->response, $interface);
        }
        $this->response = array_reverse($this->response);
    }

    private function scanForNetworks()
    {
        $interface = escapeshellarg($this->request->interface);
        if (substr($interface, -4, -1) === "mon") {
            if ($interface == "'wlan1mon'") {
                exec("killall pineap");
                exec("killall pinejector");
            }
            exec("airmon-ng stop {$interface}");
            $interface = substr($interface, 0, -4) . "'";
            exec("iw dev {$interface} scan &> /dev/null");
        }

        $uciID = $this->getUciID(substr($interface, 1, -1));
        $radio = $this->getRadioID(substr($interface, 1, -1));
        if ($this->uciGet("wireless.@wifi-iface[{$uciID}].network") === 'wan') {
            $this->uciSet("wireless.@wifi-iface[{$uciID}].network", 'lan');
            exec("wifi up $radio");
            sleep(2);
        }

        exec("iwinfo {$interface} scan", $apScan);
        
        if ($apScan[0] === 'No scan results') {
            $this->error = true;
            return;
        }

        $apArray = preg_split("/^Cell/m", implode("\n", $apScan));

        $returnArray = array();
        foreach ($apArray as $apData) {
            $apData = explode("\n", $apData);
            $accessPoint = array();
            $accessPoint['mac'] = substr($apData[0], -17);
            $accessPoint['ssid'] = substr(trim($apData[1]), 8, -1);
            if (mb_detect_encoding($accessPoint['ssid'], "auto") === false) {
                continue;
            }

            $accessPoint['channel'] = intval(substr(trim($apData[2]), -2));

            $signalString = explode("  ", trim($apData[3]));
            $accessPoint['signal'] = substr($signalString[0], 8);
            $accessPoint['quality'] = substr($signalString[1], 9);

            $security = substr(trim($apData[4]), 12);
            if ($security === "none") {
                $accessPoint['security'] = "Open";
            } else {
                $accessPoint['security'] = $security;
            }

            if ($accessPoint['mac'] && trim($apData[1]) !== "ESSID: unknown") {
                array_push($returnArray, $accessPoint);
            }
        }
        $this->response = $returnArray;
    }

    private function connectToAP()
    {
        $interface = $this->request->interface;

        if (substr($interface, -3) === "mon") {
            $interface = str_replace("mon", "", $interface);
        }

        exec('[ ! -z "$(wifi detect)" ] && wifi detect > /etc/config/wireless');

        $uciID = $this->getUciID($interface);
        $security = $this->request->ap->security;
        switch ($security) {
            case 'Open':
                $encryption = "none";
                break;
            
            case 'WPA2 (CCMP)':
            case 'WPA2 PSK (CCMP)':
                $encryption = "psk2+ccmp";
                break;

            case 'WPA2 (TKIP, CCMP)':
            case 'WPA2 PSK (TKIP, CCMP)':
                $encryption = "psk2+ccmp+tkip";
                break;

            case 'mixed WPA/WPA2 (TKIP, CCMP)':
            case 'mixed WPA/WPA2 PSK (TKIP, CCMP)':
                $encryption = "mixed-psk+ccmp+tkip";
                break;

            case 'mixed WPA/WPA2 (CCMP)':
            case 'mixed WPA/WPA2 PSK (CCMP)':
                $encryption = "mixed-psk+ccmp";
                break;
            default:
                $encryption = "";
        }

        $this->uciSet("wireless.@wifi-iface[{$uciID}].network", 'wan');
        $this->uciSet("wireless.@wifi-iface[{$uciID}].mode", 'sta');
        $this->uciSet("wireless.@wifi-iface[{$uciID}].ssid", $this->request->ap->ssid);
        $this->uciSet("wireless.@wifi-iface[{$uciID}].encryption", $encryption);
        $this->uciSet("wireless.@wifi-iface[{$uciID}].key", $this->request->key);

        $radioID = $this->getRadioID($interface);
        if ($radioID === false) {
            $this->execBackground("wifi");
        } else {
            $this->execBackground("wifi reload {$radioID}");
            $this->execBackground("wifi up {$radioID}");
        }
    }

    private function getRoutingTable()
    {
        exec('ifconfig | grep encap:Ethernet | awk "{print \$1}"', $routeInterfaces);
        exec('route', $routingTable);
        $routingTable = implode("\n", $routingTable);
        $this->response = array('routeTable' => $routingTable, 'routeInterfaces' => $routeInterfaces);
    }

    private function restartDNS()
    {
        $this->execBackground('/etc/init.d/dnsmasq restart');
        $this->response = array("success" => true);
    }

    private function updateRoute()
    {
        $routeInterface = escapeshellarg($this->request->routeInterface);
        $routeIP = escapeshellarg($this->request->routeIP);
        exec("route del default");
        exec("route add default gw {$routeIP} {$routeInterface}");
        $this->response = array("success" => true);
    }

    private function getAdvancedData()
    {
        exec("ifconfig -a", $ifconfig);
        $this->response = array("hostname" => gethostname(), "ifconfig" => implode("\n", $ifconfig));
    }

    private function setHostname()
    {
        exec("uci set system.@system[0].hostname=" . escapeshellarg($this->request->hostname));
        exec("uci commit system");
        exec("echo $(uci get system.@system[0].hostname) > /proc/sys/kernel/hostname");
        $this->response = array("success" => true);
    }

    private function resetWirelessConfig()
    {
        $this->execBackground("wifi detect > /etc/config/wireless && wifi");
        $this->response = array("success" => true);
    }

    private function getInterfaceList()
    {
        exec("ifconfig -a | grep encap:Ethernet | awk '{print \$1\",\"\$5}'", $interfaceArray);
        $this->response = $interfaceArray;
    }

    private function saveAPConfig()
    {
        $config = $this->request->apConfig;
        if (empty($config->openSSID) || empty($config->managementSSID)) {
            $this->error = "Error: SSIDs must be at least one character.";
            return;
        }
        if (strlen($config->managementKey) < 8) {
            $this->error = "Error: WPA2 Passwords must be at least 8 characters long.";
            return;
        }
        $this->uciSet('wireless.radio0.channel', $config->selectedChannel);
        $this->uciSet('wireless.@wifi-iface[0].ssid', $config->openSSID);
        $this->uciSet('wireless.@wifi-iface[0].hidden', $config->hideOpenAP);
        $this->uciSet('wireless.@wifi-iface[1].ssid', $config->managementSSID);
        $this->uciSet('wireless.@wifi-iface[1].key', $config->managementKey);
        $this->uciSet('wireless.@wifi-iface[1].disabled', $config->disableManagementAP);
        $this->execBackground('wifi');
        $this->response = array("success" => true);
    }

    private function getAPConfig()
    {
        $this->response = array(
            "selectedChannel" => $this->uciGet("wireless.radio0.channel"),
            "openSSID" => $this->uciGet("wireless.@wifi-iface[0].ssid"),
            "hideOpenAP" => $this->uciGet("wireless.@wifi-iface[0].hidden"),
            "managementSSID" => $this->uciGet("wireless.@wifi-iface[1].ssid"),
            "managementKey" => $this->uciGet("wireless.@wifi-iface[1].key"),
            "disableManagementAP" => $this->uciGet("wireless.@wifi-iface[1].disabled")
        );
    }

    private function getMacData()
    {
        $this->response = array();
        exec("ifconfig -a | grep wlan | awk '{print \$1\" \"\$5}'", $interfaceArray);
        foreach ($interfaceArray as $interface) {
            $interface = explode(" ", $interface);
            $this->response[$interface[0]] = $interface[1];
        }
    }

    private function setMac($random)
    {
        $uciID = $this->getUciID($this->request->interface);
        $interface = escapeshellarg($this->request->interface);

        if ($random) {
            $mac = exec("ifconfig {$interface} down && macchanger -A {$interface} | grep New | awk '{print \$3}'");
        } else {
            $requestMac = escapeshellarg($this->request->mac);
            $mac = exec("ifconfig {$interface} down && macchanger -m {$requestMac} {$interface} | grep New | awk '{print \$3}'");
        }

        $this->uciSet("wireless.@wifi-iface[{$uciID}].macaddr", $mac);
        exec("ifconfig {$interface} up");
        $this->response = array("success" => true, "uci" => $uciID);
    }

    private function resetMac()
    {
        $uciID = $this->getUciID($this->request->interface);
        $this->uciSet("wireless.@wifi-iface[{$uciID}].macaddr", "");
        exec("wifi");
        $this->response = array("success" => true);
    }

    private function getUciID($interface)
    {
        $interfaceNumber = str_replace("wlan", "", $interface);
        if ($interfaceNumber === "0") {
            return 0;
        } elseif ($interfaceNumber === "0-1") {
            return 1;
        } else {
            return (intval($interfaceNumber) + 1);
        }
    }

    private function getRadioID($interface)
    {
        exec('wifi status', $wifiStatus);
        $radioArray = json_decode(implode("\n", $wifiStatus));
        
        foreach ($radioArray as $radio => $radioConfig) {
            if (isset($radioConfig->interfaces[0]->config->ifname)) {
                if ($radioConfig->interfaces[0]->config->ifname === $interface) {
                    return $radio;
                }
            }
        }
        return false;
    }
}
