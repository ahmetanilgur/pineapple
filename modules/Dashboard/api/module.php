<?php namespace pineapple;

require_once('/pineapple/api/DatabaseConnection.php');

class Dashboard extends SystemModule
{
    private $dbConnection;
    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;
        if (file_exists('/tmp/landingpage.db')) {
            $this->dbConnection = new DatabaseConnection('/tmp/landingpage.db');
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'getOverviewData':
                $this->getOverviewData();
                break;

            case 'getLandingPageData':
                $this->getLandingPageData();
                break;
            
            case 'getBulletins':
                $this->getBulletins();
                break;
        }
    }

    private function getOverviewData()
    {
        $this->response = array(
            "cpu" => $this->getCpu(),
            "uptime" => $this->getUptime(),
            "clients" => $this->getClients(),
            "SSIDs" => $this->getSSIDs(),
            "newSSIDs" => $this->getNewSSIDs()
        );
    }

    private function getCpu()
    {
        $cpu = exec("top -bn1 | grep CPU -m1 | awk '{print $8}'");
        $cpu = rtrim($cpu, "%");
        return 100 - $cpu;
    }

    private function getUptime()
    {
        $seconds = intval(explode('.', file_get_contents('/proc/uptime'))[0]);
        $days = floor($seconds / (24 * 60 * 60));
        $hours = floor(($seconds % (24 * 60 * 60)) / (60 * 60));
        if ($days > 0) {
            return $days . ($days == 1 ? " day, " : " days, ") . $hours . ($hours == 1 ? " hour" : " hours");
        }
        $minutes = floor(($seconds % (60 * 60)) / 60);
        return $hours . ($hours == 1 ? " hour, " : " hours, ") . $minutes . ($minutes == 1 ? " minute" : " minutes");
    }

    private function getClients()
    {
        $clients = exec('iw dev wlan0 station dump | grep Station | wc -l');
        return $clients;
    }

    private function getSSIDs()
    {
        $SSIDs = exec("wc -l /etc/PI_napple/ssid_file | awk '{print \$1}'");
        return $SSIDs;
    }

    private function getNewSSIDs()
    {
        touch('/tmp/boot_ssid_count');
        $oldCount = intval(file_get_contents('/tmp/boot_ssid_count'));
        $currentCount = intval(exec('wc -l /etc/PI_napple/ssid_file | awk \'{print $1}\''));
        return (($currentCount - $oldCount) >= 0) ? ($currentCount - $oldCount) : 0;
    }


    private function getLandingPageData()
    {
        if ($this->dbConnection !== false) {
            $stats = array();
            $stats['Chrome'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'chrome\';'));
            $stats['Safari'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'safari\';'));
            $stats['Firefox'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'firefox\';'));
            $stats['Opera'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'opera\';'));
            $stats['Internet Explorer'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'internet_explorer\';'));
            $stats['Other'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'other\';'));
            $this->response = $stats;
        }
    }


    private function getBulletins()
    {

        $context = stream_context_create(["ssl" => ["verify_peer" => true, "cafile" => "/etc/ssl/certs/cacert.pem"]]);
        $bulletinData = @file_get_contents("https://www.wifipineapple.com/nano/bulletin", false, $context);

        if ($bulletinData !== false) {
            $this->response = json_decode($bulletinData);
            if (json_last_error() === JSON_ERROR_NONE) {
                return;
            }
        }
        
        $this->error = "Error connecting to WiFiPineapple.com. Please check your connection.";
    }
}
