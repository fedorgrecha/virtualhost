#!/usr/bin/php
<?php

if (php_sapi_name() === 'cli') {
    $apache = strlen(exec("apache2 -v"));
    $vh     = new Vhost($argv);

    if (! $apache) {
        $vh->log("ERROR: only apache server is available.", true);
    }

    switch ($argv[1]) {
        case "create":
            $vh->create();
            break;
        case "delete":
            $vh->delete();
            break;
        case "help":
        default:
            $vh->help();
            break;
    }
}

exit;

final class Vhost
{
    /**
     * get system's owner
     * @var string
     */
    private $user;

    /**
     * help command
     * @var array
     */
    private $help = ["help", "--help", "h"];

    /**
     * host name
     * @var string
     */
    private $hostName;

    /**
     * get all optional parameters
     * @var array
     */
    private $options = [];

    /**
     * dir, where host will placed
     * default /var/www
     * @var string
     */
    private $rootDir = "/var/www";

    /**
     * set directory root
     * default - root host folder
     * @var string
     */
    private $root = "";

    /**
     * set site admin email
     * @var string
     */
    private $email = "webmaster@localhost";

    /**
     * path to apache sites enabled folder
     * @var string
     */
    private $sitesEnabled = "/etc/apache2/sites-enabled/";

    /**
     * path to apache sites available folder
     * @var string
     */
    private $sitesAvailable = "/etc/apache2/sites-available/";

    /**
     * path to host conf file
     * @var string
     */
    private $siteConf;

    /**
     * absolute path to host directory
     * @var string
     */
    private $Directory;

    /**
     * absolute path to host document root
     * @var string
     */
    private $DocumentRoot;

    /**
     * path to /etc/hosts
     * @var string
     */
    private $etc = "/etc/hosts";

    /**
     * Preparing everything for readiness
     * @param $argv
     */
    public function __construct($argv)
    {
        $this->user = $this->getUser();
        
        if (exec('whoami') != 'root') {
            $this->log("ERROR: Permission denied. Run it with sudo.", true, "help");
        }
        //HELP
        if (isset($argv[2]) && in_array($argv[2], $this->getHelpArray())) {
            $this->help($argv[1]);
            exit;
        }

        if (isset($argv[2])) {
            $this->hostName = $argv[2];
        } else {
            $this->help();
            exit;
        }

        $this->setOptions($argv);
        $this->setSiteConfFile();
        $this->setAbsolutePaths();
    }

    /**
     * Creating host
     * @return void
     */
    public function create()
    {
        //creating host folder
        if (isset($this->hostName) && is_dir($this->Directory)) {
            echo "vhost \033[32m$this->hostName\033[0m already exists. \n";
            exit;
        } elseif (isset($this->hostName) && is_dir($this->rootDir)) {
            if (mkdir($this->Directory, 0755, true)) {
                exec("chown -R $this->user:$this->user $this->Directory");
                echo "Folder \033[32m$this->hostName\033[0m created on path: $this->Directory.\n";
                
                if (isset($this->options["--install"])) {
                    $this->install($this->options["--install"]);
                }
            } else {
                echo "Cannot create folder \033[31m$this->hostName\033[0m created on path: $this->Directory. Permission denied.\n";
                exit;
            }
        } else {
            echo "Unknown command. Use \033[32m help\033[0m\n";
            exit;
        }

        //check if document root exists
        $this->checkDocumentRoot();
        //creating apache conf file
        $txt = $this->getVHostConfFileContent();
        $this->writeToFile($this->siteConf, "w", $txt);

        //add host to /etc/hosts
        $this->writeToFile($this->etc, "a", PHP_EOL . "127.0.0.1 $this->hostName");

        //enable web site
        echo exec("a2ensite $this->hostName");
        echo "\n";
        //restart apache
        echo exec("/etc/init.d/apache2 reload");

        //all done
        echo "\n";
        exit;
    }

    /**
     * Deleting host
     * @return void
     */
    public function delete()
    {
        if (isset($this->hostName) && is_dir($this->Directory)) {
            $this->deleteConfirmation();
        } else {
            echo "$this->hostName not found\n";
        }
    }

    private function deleteConfirmation()
    {
        $answer = readline("Are you sure you want to delete $this->hostName? All data will be lost. (y/N)");
        if ('y' == strtolower($answer)) {
            exec("rm -rf $this->Directory");
            echo "\n$this->hostName deleted.\n";

            $etc = file($this->etc);
            for ($i = 0; $i < sizeof($etc); $i++) {
                if ($etc[$i] == "127.0.0.1 $this->hostName") {
                    unset($etc[$i]);
                }
            }

            $this->writeToFile($this->etc, "w+", $etc);

            echo "$this->hostName deleted from /etc/hosts\n";
        } elseif ('n' == strtolower($answer) || $answer == "") {
            echo "Deleting canceled.\n";
            exit;
        } else {
            echo "Wrong command. Type \"y\" OR \"n\" \n";
            $this->deleteConfirmation();
        }

        //disable web site
        echo exec("a2dissite $this->hostName");
        echo "\n";
        //restart apache
        echo "\n";
        echo exec("/etc/init.d/apache2 reload");
        echo "\n";
    }

    /**
     * Returns array of all available help commands
     * @return array
     */
    public function getHelpArray()
    {
        return $this->help;
    }

    /**
     * Getting help for command
     * @param $action
     */
    public function help($action = null)
    {
        if ($action == 'create') {
echo "sudo virtualhost create HostName [--OPTIONS]
Create host with optional parameters
For detailed help use sudo virtualhost help\n";
        } elseif ($action == 'delete') {
echo "sudo virtualhost delete HostName
Delete host from /etc/hosts and apache available site folder\n";
        } else {
echo "\033[34mUsage:\033[0m
    sudo virtualhost [ACTION] HOSTNAME [--OPTIONS]
    \e[97mAction:\e[0m
        create - create host
        delete - delete host
        
    \033[97mOptions:\033[0m
        --help, help, h
        --path - Set site path. \033[97mDefault is /var/www\033[0m
        --root - Set document root folder. \033[97mDefault is /var/www/HOSTNAME\033[0m
        --install - Installing package to root HOSTNAME`s folder
            --install=laravel - Installing latest laravel application
        
    \033[97mExample:\033[0m
        virtualhost example.com --path=/var/www --root=public --install=laravel
        it will create /var/www/example.com/public and set configs up
";
        }
        exit;
    }

    /**
     * Throw exception with message and exit from script if necessary
     * @param string $message
     * @param bool $exit
     * @param callable $callback
     */
    public function log($message, $exit = false, $callback = null)
    {
        echo $message . "\n";

        if ($callback !== null) {
            call_user_func([$this, $callback]);
        }

        if ($exit) {
            exit;
        }
    }

    /**
     * TODO: need to redesign this method
     * TODO: because, now it returns firs user - can be not current
     * Returns current machine user (not root)
     * @return mixed
     */
    private function getUser()
    {
        exec('/usr/bin/users', $output);
        return $output[0];
    }

    /**
     * Getting all options from command line
     * like as: --root --path etc
     * @param $argv
     */
    private function setOptions($argv)
    {
        //getting all options
        // from 3 because:
        // 0 - all command
        // 1 - method (create or delete)
        // 2 - host name
        for ($i = 3; isset($argv[$i]); $i++) {
            $this->options[] = $argv[$i];
        }
        if (isset($this->options)) {
            foreach ($this->options as $option) {
                list($opt, $value)   = explode("=", $option);
                $this->options[$opt] = $value;
            }
        }
        //default params
        if (isset($this->options["--path"])) {
            if ($this->options["--path"][0] != DIRECTORY_SEPARATOR) {
                exit("Path should be absolute.");
            }
            $this->rootDir = $this->options["--path"];
        }
        if (isset($this->options["--root"])) {
            $this->root = $this->options["--root"];
        }
    }

    /**
     * Set path to HOSTNAME.conf file
     */
    private function setSiteConfFile()
    {
        $this->siteConf = $this->sitesAvailable . $this->hostName . ".conf";
    }

    /**
     * Set absolute paths for host folder
     * and host`s DocRoot folder
     */
    private function setAbsolutePaths()
    {
        $rootDir = trim($this->rootDir, '/');
        $root    = trim($this->root, '/');
        $rootDir = '/'.$rootDir;

        if ($root != "") {
            $this->DocumentRoot = $rootDir.DIRECTORY_SEPARATOR.$this->hostName.DIRECTORY_SEPARATOR.$root.DIRECTORY_SEPARATOR;
        } else {
            $this->DocumentRoot = $rootDir.DIRECTORY_SEPARATOR.$this->hostName.DIRECTORY_SEPARATOR;
        }

        $this->Directory    = $rootDir.DIRECTORY_SEPARATOR.$this->hostName;
    }

    /**
     * check folder and
     * create host`s DocRoot if not exists
     */
    private function checkDocumentRoot()
    {
        //making sure that document root for installation package will set correctly
        if (isset($this->options["--install"])) {
            switch ($this->options["--install"]) {
                case "laravel":
                    if (! isset($this->options["--root"])) {
                        $this->DocumentRoot .= DIRECTORY_SEPARATOR.'public';
                    }
                    break;
                case "symfony":
                    if (! isset($this->options["--root"])) {
                        $this->DocumentRoot .= DIRECTORY_SEPARATOR.'web';
                    }
                    break;
                default:
                    break;
            }
        }
        if (! is_dir($this->DocumentRoot)) {
            mkdir($this->DocumentRoot, 0755, true);
            exec("chown -R $this->user:$this->user $this->DocumentRoot");
        }
    }

    /**
     * Generate host`s conf file content
     * @return string
     */
    private function getVHostConfFileContent()
    {
        $DocumentRoot = $this->DocumentRoot;
        $Directory    = $this->Directory;
    
$vhost = "<VirtualHost *:80>
	ServerAdmin $this->email
	ServerName  $this->hostName
	ServerAlias www.$this->hostName
	DocumentRoot $DocumentRoot
	<Directory $Directory>
        Options Indexes FollowSymLinks
        AllowOverride All
        Order allow,deny
        allow from all
	</Directory>
	ErrorLog /var/log/apache2/$this->hostName-error.log
	LogLevel error
	CustomLog /var/log/apache2/$this->hostName-access.log combined
</VirtualHost>
";
        return $vhost;
    }

    /**
     * Install Package to host`s root folder
     * @param $package
     */
    private function install($package)
    {
        if ($package == 'laravel') {
            echo exec("composer create-project --prefer-dist laravel/laravel $this->Directory");
            echo "\nAPP_NAME=\033[32mLaravel\033[0m";
            $appName = readline();
            echo "\nDB_CONNECTION=\033[32mmysql\033[0m";
            $dbConnect = readline();
            echo "\nDB_HOST=\033[32mlocalhost\033[0m";
            $dbHost = readline();
            echo "\nDB_PORT=\033[32m3306\033[0m";
            $dbPort = readline();
            echo "\nDB_DATABASE=\033[32mhomestead\033[0m";
            $dbDatabase = readline();
            echo "\nDB_USERNAME=\033[32mroot\033[0m";
            $dbUsername = readline();
            echo "\nDB_PASSWORD=\033[32mnull\033[0m";
            $dbPass = readline();
            echo "\n";

            $appName    = $appName    != "" ? $appName    : "Laravel";
            $dbConnect  = $dbConnect  != "" ? $dbConnect  : "mysql";
            $dbHost     = $dbHost     != "" ? $dbHost     : "localhost";
            $dbPort     = $dbPort     != "" ? $dbPort     : "3306";
            $dbDatabase = $dbDatabase != "" ? $dbDatabase : "homestead";
            $dbUsername = $dbUsername != "" ? $dbUsername : "homestead";
            $dbPass     = $dbPass     != "" ? $dbPass     : "secret";
            $_env = $this->getEnvContent($appName, $dbConnect, $dbHost, $dbPort, $dbDatabase, $dbUsername, $dbPass);

            $this->writeToFile($this->Directory . "/.env", "w", $_env);

            exec("chown -R $this->user:$this->user $this->Directory/");
            exec("chmod -R 0755 $this->DocumentRoot/");
            exec("chmod -R 0777 $this->Directory/storage $this->Directory/bootstrap");
            echo exec("php $this->Directory/artisan key:generate");
            echo "\n";
        }
    }

    /**
     * Generate .env file for laravel installation
     * @param $appName
     * @param $dbConnect
     * @param $dbHost
     * @param $dbPort
     * @param $dbDatabase
     * @param $dbUsername
     * @param $dbPass
     * @return string
     */
    private function getEnvContent($appName, $dbConnect, $dbHost, $dbPort, $dbDatabase, $dbUsername, $dbPass)
    {
return "APP_NAME=\"$appName\"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_LOG_LEVEL=debug
APP_URL=http://{$this->hostName}

DB_CONNECTION=$dbConnect
DB_HOST=$dbHost
DB_PORT=$dbPort
DB_DATABASE=$dbDatabase
DB_USERNAME=$dbUsername
DB_PASSWORD=$dbPass

BROADCAST_DRIVER=log
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120
QUEUE_DRIVER=sync

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
";
    }

    /**
     * Writing data to file with locking file while writing process not finished
     * @param string $file
     * @param string $mode
     * @param string | array $data
     */
    private function writeToFile($file, $mode, $data)
    {
        $f = fopen($file, $mode);
        flock($f, LOCK_EX);

        if (is_string($data)) {
            fwrite($f, $data);
        }

        if (is_array($data)) {
            foreach ($data as $string) {
                fwrite($f, $string);
            }
        }

        flock($f, LOCK_UN);
        fclose($f);
    }
}
