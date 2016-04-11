<?php namespace pineapple;

class Recon extends SystemModule
{
    private $apInterface = "wlan0";
    private $clientInterface = "wlan1";
    private $scanID = null;

    public function route()
    {
        switch ($this->request->action)
        {
            case 'scanStatus':
                $this->getScanStatus();
                break;

            case 'startScan':
                $this->startScan();
                break;
        }
    }

    private function startScan()
    {
        $this->scanID = rand(0, getrandmax());

        if (isset($this->request->scanType)) {
            $this->apScan();
            if ($this->request->scanType == "clientAP") {
                if (is_numeric($this->request->scanDuration)) {
                    if ($this->request->scanDuration < 15 || $this->request->scanDuration > 600) {
                        $this->request->scanDuration = 15;
                    }
                } else {
                    $this->request->scanDuration = 15;
                }
                $this->startMonitorMode();
                $this->clientScan($this->request->scanDuration);
            }
            $this->response = array("status" => "success", "scan" => array("scanID" => $this->scanID, "scanType" => $this->request->scanType));
        } else {
            $this->response = array("status" => "fail");
        }
    }

    private function apScan()
    {
        exec("echo \"bash -c 'for i in {1..5}; do iwinfo {$this->apInterface} scan >> /tmp/recon_ap-{$this->scanID}.tmp; sleep 1; done; mv /tmp/recon_ap-{$this->scanID}.tmp /tmp/recon_ap-{$this->scanID}'\" | at now");
    }

    private function clientScan($duration)
    {
        exec("echo 'pinesniffer {$this->clientInterface}mon {$duration} /tmp/recon_clients-{$this->scanID}' | at now");
    }

    private function startMonitorMode()
    {
        if (empty(exec("ifconfig | grep {$this->clientInterface}mon"))) {
            exec("airmon-ng start {$this->clientInterface}");
        }
    }

    private function getScanStatus()
    {
        if (isset($this->request->scan)) {
            if (file_exists("/tmp/recon_ap-{$this->request->scan->scanID}")) {
                if ($this->request->scan->scanType == "clientAP") {
                    if (file_exists("/tmp/recon_clients-{$this->request->scan->scanID}")) {
                        $this->getScanResults($this->request->scan);
                    } else {
                        $this->response = array("completed" => false);
                    }
                } else {
                    $this->getScanResults($this->request->scan);
                }
            } else {
                $this->response = array("completed" => false);
            }
        } else {
            $this->response = array("completed" => false);
        }
    }

    private function getScanResults($scan)
    {
        sleep(1);
        $apData = $this->parseAPData($scan->scanID);
        
        if ($scan->scanType === "apOnly") {
            $this->response = array("completed" => true, "ap" => $apData);
        } else {
            $clientData = $this->parseClientData($scan->scanID);
            $this->response = array("completed" => true, "ap" => $apData, "clients" => $clientData);
        }
    }

    private function parseAPData($scanID)
    {
        $fileName = "/tmp/recon_ap-{$scanID}";
        $apFile = file_get_contents($fileName);
        $apArray = preg_split("/^Cell/m", $apFile);

        $accessPoints = array();
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

            if (trim($apData[1]) === "ESSID: unknown") {
                $accessPoint['ssid'] = "";
            }

            if ($accessPoint['mac']) {
                if (!isset($accessPoints[$accessPoint['mac']])) {
                    $accessPoints[$accessPoint['mac']] = $accessPoint;
                }
            }
        }
        return $accessPoints;
    }

    private function parseClientData($scanID)
    {
        $fileName = "/tmp/recon_clients-{$scanID}";
        $clientFile = explode("\n", trim(file_get_contents($fileName)));
        
        $clientList = array();
        foreach ($clientFile as $clientLine) {
            $clientLine = explode(", ", $clientLine);
            if (!isset($clientList[$clientLine[1]])) {
                $clientList[$clientLine[1]] = array();
            }
            array_push($clientList[$clientLine[1]], $clientLine[0]);
        }

        unlink($fileName);
        return $clientList;
    }
}
