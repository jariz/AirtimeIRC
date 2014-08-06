<?php
namespace AirtimeIRC;
use Curl;
use Philip\IRC\Event;
use Philip\IRC\Response;
use Philip\Philip;
use Symfony\Component\Yaml\Yaml;
use Hoa\File\Read;

class Bot {
    /**
     * @var object The bot's config found in config.yaml
     */
    protected $config;
    /**
     * @var string Project directory
     */
    protected $rootDir;
    /**
     * @var Colors
     */
    protected $colors;

    /**
     * @var Philip
     */
    protected $irc;

    public function __construct($rootDir) {
        $this->rootDir = $rootDir;
        $this->plain("AirtimeIRC bot");

        try {
            $this->info("Loading config...");
            $this->loadConfig();

            $this->info("Initializing bot...");
            $this->init();
        } catch(\Exception $e) {
            $this->error(get_class($e).": ".$e->getMessage());
        }
    }

    public function loadConfig() {
        $config = new Read($this->rootDir."/config.yaml");
        $this->config = (object)Yaml::parse($config);
    }

    public function init() {
        $this->config->log = $this->rootDir."/irc.log";
        $this->irc = new Philip((array)$this->config);

        $this->irc->onError(function(Event $event) {
            $this->error($event->getRequest()->getMessage());
        });

        $this->irc->onNotice(function(Event $event) {
            $this->debug($event->getRequest()->getMessage());
        });

        $this->irc->onInvite(function(Event $event) {
            $chan = $event->getRequest()->getMessage();
            $event->addResponse(Response::join($chan));
            $event->addResponse(Response::msg($chan, $this->config->motd));
        });

        $this->irc->onChannel('/^!np$/', function(Event $event) {
            $chan = $event->getRequest()->getSource();
            $curl = new Curl();
            $r = $curl->get($this->config->airtimeurl."/api/live-info/");
            $radio = json_decode($curl->response);
            if($r != 0 || json_last_error() != 0 || is_null($radio)) {
                $event->addResponse(Response::msg($chan, "I couldn't contact/understand the server. Please contact my admin. :'("));
            }
            if(isset($radio->current->name)) $event->addResponse(Response::msg($chan, "NOW PLAYING: ".$radio->current->name));
            if(isset($radio->next->name)) $event->addResponse(Response::msg($chan, "NEXT UP: ".$radio->next->name));
            if(isset($radio->currentShow[0]->name)) $event->addResponse(Response::msg($chan, "CURRENT SHOW: ".$radio->currentShow[0]->name));
            if(isset($this->config->additionalmsg)) $event->addResponse(Response::msg($chan, $this->config->additionalmsg));
        });

        $this->irc->run();
    }


    public function plain($msg) {
        if(!isset($this->config->loglevel) || (isset($this->config->loglevel) && in_array("plain", $this->config->loglevel))) $this->log($msg, "white");
    }

    public function info($msg) {
        if(!isset($this->config->loglevel) || (isset($this->config->loglevel) && in_array("info", $this->config->loglevel))) $this->log($msg, "cyan");
    }

    public function error($msg) {
        if(!isset($this->config->loglevel) || (isset($this->config->loglevel) && in_array("error", $this->config->loglevel))) $this->log($msg, "light_red");
    }

    public function debug($msg) {
        if(!isset($this->config->loglevel) || (isset($this->config->loglevel) && in_array("debug", $this->config->loglevel))) $this->log($msg, "light_green");
    }

    protected function log($msg, $foreground=null, $background=null) {
        if($this->colors == null) $this->colors = new Colors();
        echo $this->colors->getColoredString($msg, $foreground, $background)."\n";
    }
}