PHP Class for TM940 statements
================================


About
-----

TM940 statements parser

Install
-------

cd to `your_project_root_dir`

```bash
composer require rozdol/mt940:"v1.*"
```

in Table or Model or Controller

```php
use Rozdol\Mt940\Mt940;
$this->mt940 = new Mt940();
$data=[...];
$result = $this->utils->mt940($data);
```

### Unit Test

`cd mt940`

```bash
composer update rozdol/mt940
```

To test
```bash
./vendor/bin/phpunit tests/
```

## Compose library Tutorial

```bash
cd mt940
composer update
git init
git .
git commit -m 'Initial commit'
```

Create github repo

```bash
git remote add origin git@github.com:rozdol/mt940.git
git push origin master
```

- on github add new release (v1.0.0)
- On packagist Update Package
- Login to [packagist.org](https://packagist.org/)
- Submit `https://github.com/rozdol/mt940`

### Ready to use in project

cd to `your_project_root_dir`

```bash
composer require rozdol/mt940
```

in Table or Model or Controller

```php
use Rozdol\Mt940\Mt940;
$this->mt940 = new Mt940();
$date_normalized = $this->mt940->F_date('01/01/20', 1); // 01.01.2020
```


#### Connecting Github to Packagist

In Github->Settings->Integrations..->Add->Packagist
user: packagist user
api_key: packagist->User->Profile->Show API KEY
Domain: https://packagist.org

Test: New reslease in Github and check the version in Packagist


### Unit Tests

install local phpunit
```bash
composer require --dev phpunit/phpunit ^6
```

```bash
mkdir tests
cd tests
mkdir TestCase
cd TestCase
mkdir Funcs
```
edit `mt940Test.php`

`cd mt940`
```bash
composer update rozdol/mt940
```

To test
```bash
./vendor/bin/phpunit tests/
```

in GitHub `Project / Settings / Services / Add Packagist`

```bash
User: rozdol
Token: https://packagist.org/profile/ -> Your API Token
Domain: https://packagist.org
Active: true
Add Service: click
```

### Travis CI

```bash
git checkout -b travis
git add .travis.yml
git push origin travis
```



in GitHub `Project / Settings / Services / Add Packagist`

```bash
User: rozdol
Token: https://travis-ci.org/profile/rozdol -> Copy API Token
Domain: notify.travis-ci.org
Active: true
Add Service: click
```

[Got to Travis](https://travis-ci.org/) Press + , swipe activation

##### Detactach from original source

```bash
git remote -v
git remote remove origin
git remote add origin git@github.com:rozdol/mt940.git
git push origin random_changes
```
