# RGM API : RGMAPI

## Presentation: What is RGMAPI?
RGM includes a web-based "RESTful" API (Application Programming Interface) called RGMAPI that enables external programs to access information from the monitoring database and to manipulate objects inside the databases of RGM suite.

In the context of the RGM HTTP API, the attribute "RESTful" essentially means:
* that it is HTTP/HTTPS based
* that it uses a set of "HTTP GET/POST" URLs to access and manipulate the data and that you'll get back an JSON document in return (for most calls).

The RGM HTTP API offers the following functionality:
* Functions for manipulating objects (e.g. edit, add, delete)

## Utilisation: How do I use RGMAPI?
All calls to the RGM HTTP API are performed by HTTP GET/POST requests. The URLs consist of a path to the API function and sometimes some parameters.

Some calls to the API are protected by API key. You need to present a valid key in your request. Each RGM user has a private APIKEY that enables to authenticate/validate the privileges.

1. Generate your APIKEY with the RGMAPI following this URI in your browser or application API call (this operation should be done one time):
```http
https://[RGM_IP]/rgmapi/getApiKey?&username=[username]&password=[password]
```
**Pre-requisites:** You have to be a local admin user (and not an LDAP user) in order to get an APIKEY from the RGMAPI. If not, RGMAPI will return an "Unauthorized" 401 response.

If authorized, you should have in return a JSON document with your **RGMAPI_KEY** value:
```json
{
    "api_version": "2.4.2",
    "http_code": "200 OK", 
    "RGMAPI_KEY": "022dfa0d83996bddada25cd01d051c6d85b64d5e383ef1f9f6cfb30e0f5b1170"
}
```
**NB:** Note the **api_version** version for implementation in your apps.

2. Test the privileges of your API key

This API call you will allow you to now if the association username/apiKey is valid & has the needed privileges.
```http
https://[RGM_IP]/rgmapi/getAuthenticationStatus?&username=[username]&apiKey=[apiKey]
```

You should have an authorized response:
```json
{
    "api_version": "2.4.2",
    "http_code": "200 OK", 
    "Status": "Authorized"
}
```

3. You can use the generated API key in your applications / API calls

There are different methods to test your API.
I recommend the Open Source client software [Postman](https://www.getpostman.com/) to test your requests and check the working of the API. Otherwise, tools like [Curl](https://curl.haxx.se/) will do the job.

A basic API call will look like that:
```http
https://[RGM_IP]/rgmapi/[API_function]?&username=[username]&apiKey=[apiKey]
```

## RGMAPI features
RGMAPI is open source and is built to make object manipulation easier. A few actions could be done remotely by calling the right API URLs.

As a reminder, a basic API call will look like that:
```http
https://[RGM_IP]/rgmapi/[API_function]?&username=[username]&apiKey=[apiKey]
```

You will find below the updated list of actions (**"API_function"**) possible in RGMAPI:

| Action URL **[API_function]** | Request type | Parameters (body/payload) | Expected response | Comments |
| --- | --- | --- | --- | --- |
| `getAuthenticationStatus` | GET | None | "status": "authorized" | Confirm that the provided user account has admin privileges and the permission to make advanced API calls. This means the association username/apiKey is correct.  |
| `createHost` | POST | [**templateHostName, hostName, hostIp, hostAlias, contactName, contactGroupName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Create a nagios host (affected to the provided parent template [templateHostName]) if not exists and reload lilac configuration. Posibility to attach a contact and/or a contact group to the host in the same time. |
| `deleteHost` | POST | [**hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Delete a nagios host. |
| `createService` | POST | [**hostName, services, exportConfiguration**] The parameter **services** is an array with the service(s) name as a key, the service template as first parameter, and the following optional service arguments linked to the service template. | "http_code": "200 OK", "result": [with the executed actions] | Add service(s) to an existant host and reload lilac configuration. To add a service, please see the parameters column. It will add a service to a specified nagios host with as many service arguments as needed. |
| `createUser` | POST | [**userName, userMail, admin, filterName, filterValue, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Create a nagios contact and a RGM user. The user could be limited or admin (depends on the parameter "admin"). Limited user: admin=false / admin user: admin=true. For a limited user, the GED xml file is created in /srv/rgm/RGMweb/cache/ with the filters specified in parameters. |
| `addContactToHost` | POST | [**contactName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Attach a nagios contact to a host if not already attached. |
| `addContactGroupToHost` | POST | [**contactGroupName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Attach a nagios contact group to a host if not already attached. |
| `createHostTemplate` | POST | [**templateHostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Create a new nagios host template. |
| `addHostTemplateToHost` | POST | [**templateHostName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Add a host template to a nagios host. |
| `addContactToHostTemplate` | POST | [**contactName, templateHostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Add a contact to a nagios host template. |
| `addContactGroupToHostTemplate` | POST | [**contactGroupName, templateHostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Add a contact group to a nagios host template. |
| `exportConfiguration` | POST | [**JobName**] | "http_code": "200 OK", "result": [with the executed actions] | Export Nagios Configuration. |


## RGMAPI calls examples
To illustrate the RGM API features tab, you will find a few implementation examples (JSON body parameters):

* /createHost
```json 
{
	"templateHostName": "TEMPLATE_HOST",
	"hostName": "HostName",
	"hostIp": "8.8.8.8",
	"hostAlias": "My first host",
	"contactName": "usertest",
	"contactGroupName": null,
	"exportConfiguration": true
}
```

* /createService
```json 
{
	"hostName": "HostName",
	"services": {
                "Service1": [
                    "TEMPLATE_SERVICE_1",
                    "127.0.0.1",
                    "eth0",
                    "1000000",
                    "100",
                    "110"
                ],
                "Service2": [
                    "TEMPLATE_SERVICE_2",
                    "3000",
                    "80",
                    "5000",
                    "90"
                ]
        },
	"exportConfiguration": true
}

```

* /createUser
```json 
{
	"userName": "bob",
	"userMail": "bob@marley.com",
	"admin": true,
	"filterName": "hostgroups",
	"filterValue": "HOSTGROUP_JAMAICA",
	"exportConfiguration": true
}
```

* /addContactToHost
```json 
{
	"contactName": "bob",
	"hostName": "HostName",
	"exportConfiguration": true
}
```

* /addContactGroupToHost
```json 
{
	"contactGroupName": "admins",
	"hostName": "HostName",
	"exportConfiguration": true
}
```

* /createHostTemplate
```json 
{
	"templateHostName": "TEMPLATE_HOST",
	"exportConfiguration": true
}
```

* /addHostTemplateToHost
```json 
{
	"templateHostName": "TEMPLATE_HOST",
	"hostName": "HostName",
	"exportConfiguration": true
}
```

* /addContactToHostTemplate
```json 
{
	"contactName": "bob",
	"templateHostName": "TEMPLATE_HOST",
	"exportConfiguration": true
}
```

* /addContactGroupToHostTemplate
```json 
{
	"contactGroupName": "admins",
	"templateHostName": "TEMPLATE_HOST",
	"exportConfiguration": true
}
```

**NB:** You should notice the optional parameter `exportConfiguration` (boolean true or false) that allows the nagios configuration export. An API call doesn't need systematically a nagios configuration reload. That's why you should set this parameter depending your needs.

## Add RGMAPI features: How to do this?
The RGM API is an open source project. You can obviously add features to fit your needs. Do not hesitate to share your version with the RGM community.

The REST API is mainly based on function calls. The functions are defined in the [ObjectManager.php](include/ObjectManager.php) file. To make these functions available remotely (http calls via token), we declare the ObjectManager function needed in [index.php](html/api/index.php) by adding a route.

A "framework" has been developped in order to add routes very easily.
The function `addRoute($httpMethod, $routeName, $methodName)` allow you to generate the route and function automatically, based on the ObjectManager method.

Example:
```php
#index.php
addRoute('post', '/createHost', 'createHost' );
```
**NB:**
The `$methodName` parameter is the Action URL (route call) defined in the [features array](#rgmapi-features). It must have the same name as the method defined in [ObjectManager.php](include/ObjectManager.php).

## Security and Encryption
If you are accessing the API inside your secure LAN you can simply use HTTP. In insecure environments (e.g. when accessing your RGM server across the Internet) you should use HTTPS requests to make sure that your parameters and passwords are encrypted. This way all communication between the RGM server and your client is encrypted by SSL encryption.

## Versioning
Most JSON replies from the API contain a **"api_version"** field that contains the api version on the RGM server. Your applications developers should take note of this version for compatibility reasons.

## Error Handling
Each response to an API call contains a status code. These status codes have a meaning and are referenced in the table below:

| Status Code | Meaning | Comments |
| --- | --- | --- |
| `200` | OK | The API call was completed successfully, the JSON response contains the result data. |
| `400` | Bad Request | The API call could not be completed successfully. The XML response contains the error message. |
| `401` | Unauthorized | The username/password or username/apiKey credentials of your authentication can not be accepted. |

## About the RGMAPI
The RGM API is built with Slim Framework.

## About Slim Framework
Slim is a PHP micro framework that helps you quickly write simple yet powerful web applications and APIs.
Slim Framework source sode https://www.slimframework.com/.

**Slim version:** `2.4.2`

**Dependenties:**
`PHP >= 5.3.0`

**Compatibility matrix:**

| Version | Comments |
| --- | --- |
| `PHP 5.3` | Tested |
| `PHP 5.4` | Tested |
| `PHP 5.5` | Tested |
| `PHP > 5.5` | Not tested |

## License
* RGM is licensed under the GNU General Public License.
* The Slim Framework is licensed under the MIT license.
