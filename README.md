## PHP SLIM Request parameter validation

Validates request query ($_GET) and body ($_BODY) parameters using regular expressions. 
Adds a layer of security and self-documentation to your API-code. 

Uses PHP 7 syntax.

Implement in 4 steps.

#### 0. Install with [Composer](https://packagist.org/packages/patricksavalle/slim-request-params) ####

- Update your `composer.json` to require `palanik/corsslim` package.
- Run `composer install` to add slim-request-params your vendor folder.
    ```json
    {
      "require": {
        "patricksavalle/slim-request-params": "dev-master"
      }
    }
    ```
- Include in your source.
    ```php
    <?php
   
    require './vendor/autoload.php';
    ```

#### 1. Add the middleware to the SLIM routes 

To validate request parameters:

    require 'vendor/autoload.php';

    use SlimRequestParams\QueryParameters;

    $slimapp = new \Slim\Slim();
    $slimapp->get(...)
        ->add(new QueryParameters([
            '{text:[\w-.~@]+}',
            '{mimetype:[\w-.\/]+}',
            '{author:[\w-. @]+}',
            '{orderby:\w+},id',
            '{reversed:1},1',
            '{offset:\int},1',
            '{count:\int},100',
            '{*}',
        ])

To validate body parameters:

    $slimapp->post(...)
        ->add(new BodyParameters([
            '{recipient:.*}',
            '{sender:.*}',
            '{subject:.*}',
            '{timestamp:.*}',
            '{token:.*}',
            '{signature:.*}',
            '{*}',
        ]));

To forbid arguments to a route:

    $slimapp->get(...)
        ->add(new QueryParameters)
        
General format of a validation rule:

    {<name>:<regex>}[,<default_value>]

Missing parameters are set to the given default or null if no default is given. 
Extra parameters generate an error unless the wildcard parameters is used: `{*}` in which 
case they are passed without validation.

Accepts the RFC-standard query parameter array, not the PHP version:

    /someurl?A=10&a=11&a=12

For typed parameters and special formats there are the following keywords that can be used instead of the regex:

    \boolean
    \int
    \float
    \date
    \raw
    \base64json
    \url
    \email
    \country
    \nationality
    \timezone
    \currency
    \language

#### 2. Install the strategy for access to validated arguments

Add the strategy that combines the url-, query- and post-parameters into one object.

    $slimapp->getContainer()['foundHandler'] = function () {
        return new RequestResponseArgsObject;
    };        

#### 3. Adapt your method handlers

A complete example.

    <?php
    require 'vendor/autoload.php';
    
    use \Psr\Http\Message\ServerRequestInterface as Request;
    use \Psr\Http\Message\ResponseInterface as Response;
    use \SlimRequestParams\BodyParameters;
    use \SlimRequestParams\QueryParameters;
    use \SlimRequestParams\RequestResponseArgsObject;
       
    $app = new \Slim\App;
    
    $app->getContainer()['foundHandler'] = function () {
        return new RequestResponseArgsObject;
    };        

    $app->get('/hello/{name}', function (Request $request, Response $response, \stdClass $args) {
        $name = $args->name;
        $text = $args->text;
        $response->getBody()->write("$text, $name");
        return $response;
    })        
        ->add(new QueryParameters(['{text:[\w-.~@]+},Hello'])

    $app->run();

This API has one method that accepts a single argument:

    /hello/patrick?text=who+am+I

To retrieve or inspect the parameters from anywhere in your app just use:
    
    \SlimRequestParams\QueryParameters::get();
    \SlimRequestParams\BodyParameters::get();

