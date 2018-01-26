## VirtualHost creating script
#### Only Apache server is suported

#### Allow you to create local Vhost on your machine

## Requirements
* Apache server installed
* php interpreter installed
* composer (for installing scripts)

## Installation
```
sudo cp vh.php /usr/local/bin/virtualhost
sudo chmod +x /usr/local/bin/virtualhost
```

## Usage
```
sudo virtualhost help
sudo virtualhost [create | delete] help
sudo virtualhost [ACTION] HOSTNAME [--OPTIONS]
```
> Actions available:
* create
* delete
> Options available: 
* --path - path to hosts folder. default is /var/www
* --root - document root folder. default is HOSTNAME's folder
* --install - installing scripts. now, only laravel installation is availabe

## Examples
```
sudo virtualhost create example.com --root=public_html
```
it will create host example.com with public_html folder as document root

```
sudo virtualhost delete example.com
```
```
sudo virtualhost create test.com --install=laravel
```