# RGM API : RGMAPI

## Presentation
RGM includes a web-based "RESTful" API (Application Programming Interface) called RGMAPI that enables external programs to access information from the monitoring database and to manipulate objects inside the databases of RGM suite.

In the context of the RGM HTTP API, the attribute "RESTful" essentially means:
* that it is HTTP/HTTPS based
* that it uses a set of "HTTP GET/POST" URLs to access and manipulate the data and that you'll get back with JSON document in return (for most calls).

The RGM HTTP API offers the following features:
* RGM authentication with user/password
* one-time token limited to specific actions
* set of functions to manipulate Nagios (Lilac) objects (eg. list, add, modify, delete)

## Usage
All calls to the RGM HTTP API are performed by HTTP GET/POST requests. The URLs consist of a path to the API function and sometimes some mandatory/optional parameters.

Some calls to the API are requiring a valid **token**. The token can be passed either as a _URI parameter_ *or* as a _HTTP header_. In both case the parameter name is ```token```. 
You need to provide a valid token for authenticated requests. A token have a limited TTL (Time To Live) period (by default 3600 seconds) and will be purged after its expiration.

## Complete API documentation

The RGMAPI is fully documented using Doxygen. The Doxygen documentation should be available into the ```api``` sub-folder. Example: ```https://localhost/rgmapi/doc```

## RGM Authentication token

### Normal username derivated token
The normal usage of the API implies a *registered* RGM user. To authenticate a RGM user, call the request ```getAuthToken``` with *GET* method, and *username* and *password* parameters.

Example, using cURL command-line utility to forge the request:

```shell
curl -k https://127.0.0.1/rgmapi/getAuthToken?&username=admin&password=my_smart_password
```
Assuming you provided a correct username/password, it should return something similar to:
```json
{
    "slim_version": "2.4.2",
    "rgmapi_version": "1.0",
    "http_code": "200 OK",
    "RGMAPI_TOKEN": "87523048d9821fa4660d162f5bd10a227f7c1d8b19c11309a6cc7d5a0f13a00b"
}
```
**NB:** Note the **rgmapi_version** version for implementation in your apps.

### One-time token
The One-time token is a special token. As its name let suppose, this token is valid only at its first call and expire directly after. Moreover its usage is restricted to very spicific actions (eg. createHost).
These tokens are automatically generated by RGM to ease _assets deployments_.

### Token usage
You are free to provide the token as URI parameter *or* as HTTP header. Note that the URI paramter have the precedence.

Example of URI param, using cURL:
```
curl -k 'https://127.0.0.1/rgmapi/checkAuthToken?&token=87523048d9821fa4660d162f5bd10a227f7c1d8b19c11309a6cc7d5a0f13a00b'
```
Example of HTTP header, using cURL:
```
curl -k -H 'token: 87523048d9821fa4660d162f5bd10a227f7c1d8b19c11309a6cc7d5a0f13a00b' 'https://127.0.0.1/rgmapi/checkAuthToken'
```

### Check token status

This API call you allows you to check if the provided token is valid & have the neequired privileges.
```http
https://[RGM_IP]/rgmapi/checkAuthToken?token=[token]
```

You should have an authorized response:
```json
{
	"slim_version": "2.4.2",
    "rgmapi_version": "1.0",
    "http_code": "200 OK",
    "session_id": "1729011985",
    "user_id": "1",
    "creation_epoch": "1558975532",
    "status": "authorized"
}
```

3. You can use the generated API key in your applications / API calls

There are different methods to test your API.
I recommend the Open Source client software [Postman](https://www.getpostman.com/) to test your requests and check the working of the API. Otherwise, tools like [Curl](https://curl.haxx.se/) will do the job.

A basic API call will look like that:
```http
https://[RGM_IP]/rgmapi/[API_function]?&username=[username]&token=[token]
```

## RGMAPI features
RGMAPI is open source and is built to make object manipulation easier. A few actions could be done remotely by calling the right API URLs.

As a reminder, a basic API call will look like that:
```http
https://[RGM_IP]/rgmapi/[API_function]?&username=[username]&token=[token]
```

You will find below the updated list of actions (**"API_function"**) possible in RGMAPI:

| Action URL **[API_function]** | Request type | Parameters (body/payload) | Expected response | Comments |
| --- | --- | --- | --- | --- |
| `checkAuthToken` | GET | None | "status": "authorized" | Confirm that the provided token has admin privileges and the permission to make advanced API calls. |
| `createHost` | POST | [**templateHostName, hostName, hostIp, hostAlias, contactName, contactGroupName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Create a nagios host (affected to the provided parent template [templateHostName]) if not exists and reload lilac configuration. Posibility to attach a contact and/or a contact group to the host in the same time. |
| `deleteHost` | POST | [**hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Delete a nagios host. |
| `deleteParentFromExistingHost` | POST | [**ParentName,hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Delete a nagios host. |
| `createService` | POST | [**hostName, services, exportConfiguration**] The parameter **services** is an array with the service(s) name as a key, the service template as first parameter, and the following optional service arguments linked to the service template. | "http_code": "200 OK", "result": [with the executed actions] | Add service(s) to an existant host and reload lilac configuration. To add a service, please see the parameters column. It will add a service to a specified nagios host with as many service arguments as needed. |
| `createUser` | POST | [**userName, userMail, admin, filterName, filterValue, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Create a nagios contact and a RGM user. The user could be limited or admin (depends on the parameter "admin"). Limited user: admin=false / admin user: admin=true. For a limited user, the GED xml file is created in /srv/rgm/RGMweb/cache/ with the filters specified in parameters. |
| `addContactToHost` | POST | [**contactName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Attach a nagios contact to a host if not already attached. |
| `addParentToHost` | POST | [**ParentName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Attach a nagios contact to a host if not already attached. |
| `addContactGroupToHost` | POST | [**contactGroupName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Attach a nagios contact group to a host if not already attached. |
| `createHostTemplate` | POST | [**templateHostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Create a new nagios host template. |
| `addHostTemplateToHost` | POST | [**templateHostName, hostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Add a host template to a nagios host. |
| `addContactToHostTemplate` | POST | [**contactName, templateHostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Add a contact to a nagios host template. |
| `addContactGroupToHostTemplate` | POST | [**contactGroupName, templateHostName, exportConfiguration**] | "http_code": "200 OK", "result": [with the executed actions] | Add a contact group to a nagios host template. |
| `exportConfiguration` | POST | [**jobName**] | "http_code": "200 OK", "result": [with the executed actions] | Export Nagios Configuration. |
| `getHostByAddress` | POST | [**hostAddress**] | "http_code": "200 OK", "result": [List of host with Address] | Return a list. |


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

* /addParentToHost
```json 
{
	"ParentName": "bob",
	"hostName": "HostName",
	"exportConfiguration": true
}
```

* /deleteParentFromExistingHost
```json 
{
	"ParentName": "bob",
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

* /getHostByAddress
```json 
{
	"hostAddress": "127.0.0.1"
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
| `401` | Unauthorized | The username/password or token credentials of your authentication can not be accepted. |

## About the RGMAPI
The RGM API is built with Slim Framework.

## About Slim Framework
Slim is a PHP micro framework that helps you quickly write simple yet powerful web applications and APIs.
Slim Framework source sode https://www.slimframework.com/.

**Slim version:** `2.4.2`

**Dependenties:**
`PHP >= 5.3.0`

## License
* RGM is licensed under the GNU General Public License.
* The Slim Framework is licensed under the MIT license.
