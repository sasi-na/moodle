<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Amazon S3 PHP class
 *
 * @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 * @version 0.5.1
 * @package block_zoomonline
 * @copyright 2013 Donovan Schönknecht
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * $Id$
 *
 * Copyright (c) 2013, Donovan Schönknecht.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * Amazon S3 is a trademark of Amazon.com, Inc. or its affiliates.
 */
class simple_storage_service {
    /**
     * Access Control List (ACL) flag for private access.
     *
     * @var string
     */
    const ACL_PRIVATE = 'private';

    /**
     * Access Control List (ACL) flag for public read access.
     *
     * @var string
     */
    const ACL_PUBLIC_READ = 'public-read';

    /**
     * Access Control List (ACL) flag for public read and write access.
     *
     * @var string
     */
    const ACL_PUBLIC_READ_WRITE = 'public-read-write';

    /**
     * Access Control List (ACL) flag for authenticated read access.
     *
     * @var string
     */
    const ACL_AUTHENTICATED_READ = 'authenticated-read';

    /**
     * Storage class for standard storage.
     *
     * @var string
     */
    const STORAGE_CLASS_STANDARD = 'STANDARD';

    /**
     * Storage class for reduced redundancy storage.
     *
     * @var string
     */
    const STORAGE_CLASS_RRS = 'REDUCED_REDUNDANCY';

    /**
     * Storage class for standard infrequent access storage.
     *
     * @var string
     */
    const STORAGE_CLASS_STANDARD_IA = 'STANDARD_IA';

    /**
     * No server-side encryption.
     *
     * @var string
     */
    const SSE_NONE = '';

    /**
     * Server-side encryption with AES256.
     *
     * @var string
     */
    const SSE_AES256 = 'AES256';

    /**
     * Default delimiter to be used, for example while getBucket().
     *
     * @var string|null
     */
    public static $defdelimiter = null;

    /**
     * AWS URI
     *
     * @var string
     */
    public static $endpoint = 's3.amazonaws.com';

    /**
     * AWS Region
     *
     * @var string
     */
    public static $region = '';

    /**
     * Proxy information
     *
     * @var null|array
     */
    public static $proxy = null;

    /**
     * Connect using SSL?
     *
     * @var bool
     */
    public static $usessl = false;

    /**
     * Use SSL validation?
     *
     * @var bool
     */
    public static $usesslvalidation = true;

    /**
     * Use SSL version
     *
     * @var int
     */
    public static $usesslversion = CURL_SSLVERSION_TLSv1;

    /**
     * Use PHP exceptions?
     *
     * @var bool
     */
    public static $useexceptions = false;

    /**
     * SSL client certificate
     *
     * @var string|null
     */
    public static $sslcert = null;

    /**
     * SSL CA cert (only required if you are having problems with your system CA cert)
     *
     * @var string|null
     */
    public static $sslcacert = null;

    /**
     * CURL progress function callback
     *
     * @var callable|null
     */
    public static $progressfunction = null;

    /**
     * AWS Signature Version
     *
     * @var string
     */
    public static $signver = 'v2';

    /**
     * The AWS Access key
     *
     * @var string
     * @access private
     * @static
     */
    private static $accesskey = null;

    /**
     * AWS Secret Key
     *
     * @var string
     * @access private
     * @static
     */
    private static $secretkey = null;

    /**
     * Time offset applied to time()
     *
     * @var int
     */
    private static $timeoffset = 0;

    /**
     * AWS Key Pair ID
     *
     * @var string
     * @access private
     * @static
     */
    private static $signingkeypairid = null;

    /**
     * Key resource, freeSigningKey() must be called to clear it from memory
     *
     * @var bool
     * @access private
     * @static
     */
    private static $signingkeyresource = false;

    /**
     * Constructor - if you're not using the class statically
     *
     * @param string $accesskey Access key
     * @param string $secretkey Secret key
     * @param boolean $usessl Enable SSL
     * @param string $endpoint Amazon URI
     * @param string $region AWS region
     * @return void
     */
    public function __construct($accesskey = null, $secretkey = null, $usessl = false, $endpoint = 's3.amazonaws.com',
            $region = '') {
        if ($accesskey !== null && $secretkey !== null) {
            self::setauth($accesskey, $secretkey);
        }
        self::$usessl = $usessl;
        self::$endpoint = $endpoint;
        self::$region = $region;
    }

    /**
     * Set AWS access key and secret key
     *
     * @param string $accesskey Access key
     * @param string $secretkey Secret key
     * @return void
     */
    public static function setauth($accesskey, $secretkey) {
        self::$accesskey = $accesskey;
        self::$secretkey = $secretkey;
    }

    /**
     * Check if AWS keys have been set
     *
     * @return boolean
     */
    public static function hasauth() {
        return (self::$accesskey !== null && self::$secretkey !== null);
    }

    /**
     * Set SSL on or off
     *
     * @param boolean $enabled SSL enabled
     * @param boolean $validate SSL certificate validation
     * @return void
     */
    public static function setssl($enabled, $validate = true) {
        self::$usessl = $enabled;
        self::$usesslvalidation = $validate;
    }

    /**
     * Set SSL client certificates (experimental)
     *
     * @param string $sslcert SSL client certificate
     * @param string $sslkey SSL client key
     * @param string $sslcacert SSL CA cert (only required if you are having problems with your system CA cert)
     * @return void
     */
    public static function setsslauth($sslcert = null, $sslkey = null, $sslcacert = null) {
        self::$sslcert = $sslcert;
        self::$sslkey = $sslkey;
        self::$sslcacert = $sslcacert;
    }

    /**
     * Set proxy information
     *
     * @param string $host Proxy hostname and port (localhost:1234)
     * @param string $user Proxy username
     * @param string $pass Proxy password
     * @param constant $type CURL proxy type
     * @return void
     */
    public static function setproxy($host, $user = null, $pass = null, $type = CURLPROXY_SOCKS5) {
        self::$proxy = ['host' => $host, 'type' => $type, 'user' => $user, 'pass' => $pass];
    }

    /**
     * Set the error mode to exceptions
     *
     * @param boolean $enabled Enable exceptions
     * @return void
     */
    public static function setexceptions($enabled = true) {
        self::$useexceptions = $enabled;
    }

    /**
     * Set AWS time correction offset (use carefully)
     *
     * This can be used when an inaccurate system time is generating
     * invalid request signatures.  It should only be used as a last
     * resort when the system time cannot be changed.
     *
     * @param string $offset Time offset (set to zero to use AWS server time)
     * @return void
     */
    public static function settimecorrectionoffset($offset = 0) {
        if ($offset == 0) {
            $rest = new S3CUSTOMRequest('HEAD');
            $rest = $rest->getresponse();
            $awstime = $rest->headers['date'];
            $systime = time();
            $offset = $systime > $awstime ? -($systime - $awstime) : ($awstime - $systime);
        }
        self::$timeoffset = $offset;
    }

    /**
     * Set signing key
     *
     * @param string $keypairid AWS Key Pair ID
     * @param string $signingkey Private Key
     * @param boolean $isfile Load private key from file, set to false to load string
     * @return boolean
     */
    public static function setsigningkey($keypairid, $signingkey, $isfile = true) {
        self::$signingkeypairid = $keypairid;
        if ((self::$signingkeyresource = openssl_pkey_get_private($isfile ?
                        file_get_contents($signingkey) : $signingkey)) !== false) {
            return true;
        }
        self::triggererror('simple_storage_service::setsigningkey(): Unable to open load private key: ' . $signingkey, __FILE__,
                __LINE__);
        return false;
    }

    /**
     * Internal error handler
     *
     * @param string $message Error message
     * @param string $file Filename
     * @param integer $line Line number
     * @param integer $code Error code
     * @return void
     * @internal Internal error handler
     */
    private static function triggererror($message, $file, $line, $code = 0) {
        if (self::$useexceptions) {
            throw new S3CustomException($message, $file, $line, $code);
        } else {
            trigger_error($message, E_USER_WARNING);
        }
    }

    /**
     * Set Signature Version
     *
     * @param string $version of signature ('v4' or 'v2')
     * @return void
     */
    public static function setsignatureversion($version = 'v4') {
        self::$signver = $version;
    }

    /**
     * Free signing key from memory, MUST be called if you are using setSigningKey()
     *
     * @return void
     */
    public static function freesigningkey() {
        if (self::$signingkeyresource !== false) {
            openssl_free_key(self::$signingkeyresource);
        }
    }

    /**
     * Set progress function
     *
     * @param function $func Progress function
     * @return void
     */
    public static function setprogressfunction($func = null) {
        self::$progressfunction = $func;
    }

    /**
     * Get a list of buckets
     *
     * @param boolean $detailed Returns detailed bucket list when true
     * @return array | false
     */
    public static function listbuckets($detailed = false) {
        $rest = new S3CUSTOMRequest('GET', '', '', self::$endpoint);
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::listbuckets(): [%s] %s", $rest->error['code'],
                    $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        $results = [];
        if (!isset($rest->body->Buckets)) {
            return $results;
        }

        if ($detailed) {
            if (isset($rest->body->Owner, $rest->body->Owner->ID, $rest->body->Owner->DisplayName)) {
                $results['owner'] = [
                        'id' => (string) $rest->body->Owner->ID, 'name' => (string) $rest->body->Owner->DisplayName,
                ];
            }
            $results['buckets'] = [];
            foreach ($rest->body->Buckets->Bucket as $b) {
                $results['buckets'][] = [
                        'name' => (string) $b->Name, 'time' => strtotime((string) $b->CreationDate),
                ];
            }
        } else {
            foreach ($rest->body->Buckets->Bucket as $b) {
                $results[] = (string) $b->Name;
            }
        }

        return $results;
    }

    /**
     * Get contents for a bucket
     *
     * If maxkeys is null this method will loop through truncated result sets
     *
     * @param string $bucket Bucket name
     * @param string $prefix Prefix
     * @param string $marker Marker (last file listed)
     * @param string $maxkeys Max keys (maximum number of keys to return)
     * @param string $delimiter Delimiter
     * @param boolean $returncommonprefixes Set to true to return CommonPrefixes
     * @return array | false
     */
    public static function getbucket($bucket, $prefix = null, $marker = null, $maxkeys = null, $delimiter = null,
            $returncommonprefixes = false) {
        $rest = new S3CUSTOMRequest('GET', $bucket, '', self::$endpoint);
        if ($maxkeys == 0) {
            $maxkeys = null;
        }
        if ($prefix !== null && $prefix !== '') {
            $rest->setparameter('prefix', $prefix);
        }
        if ($marker !== null && $marker !== '') {
            $rest->setparameter('marker', $marker);
        }
        if ($maxkeys !== null && $maxkeys !== '') {
            $rest->setparameter('max-keys', $maxkeys);
        }
        if ($delimiter !== null && $delimiter !== '') {
            $rest->setparameter('delimiter', $delimiter);
        } else if (!empty(self::$defdelimiter)) {
            $rest->setparameter('delimiter', self::$defdelimiter);
        }
        $response = $rest->getresponse();
        if ($response->error === false && $response->code !== 200) {
            $response->error = ['code' => $response->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($response->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getbucket(): [%s] %s",
                    $response->error['code'], $response->error['message']), __FILE__, __LINE__);
            return false;
        }

        $results = [];

        $nextmarker = null;
        if (isset($response->body, $response->body->Contents)) {
            foreach ($response->body->Contents as $c) {
                $results[(string) $c->Key] = [
                        'name' => (string) $c->Key,
                        'time' => strtotime((string) $c->LastModified),
                        'size' => (int) $c->Size,
                        'hash' => substr((string) $c->ETag, 1, -1),
                        'storageclass' => (string) $c->StorageClass,
                ];
                $nextmarker = (string) $c->Key;
            }
        }

        if ($returncommonprefixes && isset($response->body, $response->body->CommonPrefixes)) {
            foreach ($response->body->CommonPrefixes as $c) {
                $results[(string) $c->Prefix] = ['prefix' => (string) $c->Prefix];
            }
        }

        if (isset($response->body, $response->body->IsTruncated) &&
                (string) $response->body->IsTruncated == 'false') {
            return $results;
        }

        if (isset($response->body, $response->body->NextMarker)) {
            $nextmarker = (string) $response->body->NextMarker;
        }

        if ($maxkeys == null && $nextmarker !== null && (string) $response->body->IsTruncated == 'true') {
            do {
                $rest = new S3CUSTOMRequest('GET', $bucket, '', self::$endpoint);
                if ($prefix !== null && $prefix !== '') {
                    $rest->setparameter('prefix', $prefix);
                }
                $rest->setparameter('marker', $nextmarker);
                if ($delimiter !== null && $delimiter !== '') {
                    $rest->setparameter('delimiter', $delimiter);
                }

                if (($response = $rest->getresponse()) == false || $response->code !== 200) {
                    break;
                }

                if (isset($response->body, $response->body->Contents)) {
                    foreach ($response->body->Contents as $c) {
                        $results[(string) $c->Key] = [
                                'name' => (string) $c->Key,
                                'time' => strtotime((string) $c->LastModified),
                                'size' => (int) $c->Size,
                                'hash' => substr((string) $c->ETag, 1, -1),
                                'storageclass' => (string) $c->StorageClass,
                        ];
                        $nextmarker = (string) $c->Key;
                    }
                }

                if ($returncommonprefixes && isset($response->body, $response->body->CommonPrefixes)) {
                    foreach ($response->body->CommonPrefixes as $c) {
                        $results[(string) $c->Prefix] = ['prefix' => (string) $c->Prefix];
                    }
                }

                if (isset($response->body, $response->body->NextMarker)) {
                    $nextmarker = (string) $response->body->NextMarker;
                }

            } while ($response !== false && (string) $response->body->IsTruncated == 'true');
        }

        return $results;
    }

    /**
     * Put a bucket
     *
     * @param string $bucket Bucket name
     * @param constant $acl ACL flag
     * @param string $location Set as "EU" to create buckets hosted in Europe
     * @return boolean
     */
    public static function putbucket($bucket, $acl = self::ACL_PRIVATE, $location = false) {
        $rest = new S3CUSTOMRequest('PUT', $bucket, '', self::$endpoint);
        $rest->setamzheader('x-amz-acl', $acl);

        if ($location === false) {
            $location = self::getregion();
        }

        if ($location !== false && $location !== "us-east-1") {
            $dom = new DOMDocument;
            $createbucketconfiguration = $dom->createElement('CreateBucketConfiguration');
            $locationconstraint = $dom->createElement('LocationConstraint', $location);
            $createbucketconfiguration->appendChild($locationconstraint);
            $dom->appendChild($createbucketconfiguration);
            $rest->data = $dom->saveXML();
            $rest->size = strlen($rest->data);
            $rest->setheader('Content-Type', 'application/xml');
        }
        $rest = $rest->getresponse();

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::putbucket({$bucket}, {$acl}, {$location}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Get the service region
     *
     * @return string $region
     * @static
     */
    public static function getregion() {
        $region = self::$region;

        if (empty($region)) {
            if (preg_match("/s3[.-](?:website-|dualstack\.)?(.+)\.amazonaws\.com/i", self::$endpoint, $match) !== 0 &&
                    strtolower($match[1]) !== "external-1") {
                $region = $match[1];
            }
        }

        return empty($region) ? 'us-east-1' : $region;
    }

    /**
     * Delete an empty bucket
     *
     * @param string $bucket Bucket name
     * @return boolean
     */
    public static function deletebucket($bucket) {
        $rest = new S3CUSTOMRequest('DELETE', $bucket, '', self::$endpoint);
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 204) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::deletebucket({$bucket}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Create input array info for putObject() with a resource
     *
     * @param string $resource Input resource to read from
     * @param integer $buffersize Input byte size
     * @param string $md5sum MD5 hash to send (optional)
     * @return array | false
     */
    public static function inputresource(&$resource, $buffersize = false, $md5sum = '') {
        if (!is_resource($resource) || (int) $buffersize < 0) {
            self::triggererror('simple_storage_service::inputresource(): Invalid resource or buffer size', __FILE__, __LINE__);
            return false;
        }

        if ($buffersize === false) {
            if (fseek($resource, 0, SEEK_END) < 0 || ($buffersize = ftell($resource)) === false) {
                self::triggererror('simple_storage_service::inputresource(): Unable to obtain resource size', __FILE__, __LINE__);
                return false;
            }
            fseek($resource, 0);
        }

        $input = ['size' => $buffersize, 'md5sum' => $md5sum];
        $input['fp'] =& $resource;
        return $input;
    }

    /**
     * Put an object from a file (legacy function)
     *
     * @param string $file Input file path
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param constant $acl ACL constant
     * @param array $metaheaders Array of x-amz-meta-* headers
     * @param string $contenttype Content type
     * @return boolean
     */
    public static function putobjectfile($file, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaheaders = [], $contenttype = null) {
        return self::putobject(self::inputfile($file), $bucket, $uri, $acl, $metaheaders, $contenttype);
    }

    /**
     * Put an object
     *
     * @param mixed $input Input data (can be string, file resource, or array with 'file', 'fp', or 'data' keys)
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param string $acl ACL constant (default: ACL_PRIVATE)
     * @param array $metaheaders Array of x-amz-meta-* headers
     * @param array|string $requestheaders Array of request headers or content type as a string
     * @param string $storageclass Storage class constant (default: STORAGE_CLASS_STANDARD)
     * @param string $serversideencryption Server-side encryption (default: SSE_NONE)
     * @return boolean True on success, false on failure
     */
    public static function putobject($input, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaheaders = [], $requestheaders = [],
            $storageclass = self::STORAGE_CLASS_STANDARD, $serversideencryption = self::SSE_NONE) {

        self::$signver = 'v4';

        if ($input === false) {
            die("ERROR: Input is false. Exiting.<br>");
        }

        $awsregion = get_config('block_zoomonline', 'aws_region');

        if (!empty($awsregion)) {
            self::$endpoint = "s3.$awsregion.amazonaws.com";
        }

        $rest = new S3CUSTOMRequest('PUT', $bucket, $uri, self::$endpoint);

        if (!is_array($input)) {
            $input = [
                    'data' => $input,
                    'size' => strlen($input),
                    'md5sum' => base64_encode(md5($input, true)),
                    'sha256sum' => hash('sha256', $input),
            ];
        }

        // Attach file pointer
        if (isset($input['fp'])) {
            $rest->fp =& $input['fp'];
        } else if (isset($input['file'])) {
            $rest->fp = @fopen($input['file'], 'rb');

            if (!$rest->fp) {
                die("ERROR: Failed to open file {$input['file']} for reading.<br>");
            }

        } else if (isset($input['data'])) {
            $rest->data = $input['data'];
        } else {
            die("ERROR: No valid input source found for S3 upload.<br>");
        }

        if (isset($input['size']) && $input['size'] >= 0) {
            $rest->size = $input['size'];
        } else {
            if (isset($input['file'])) {
                clearstatcache(false, $input['file']);
                $rest->size = filesize($input['file']);
            } else if (isset($input['data'])) {
                $rest->size = strlen($input['data']);
            }
        }
        if (is_array($requestheaders)) {
            foreach ($requestheaders as $h => $v) {
                strpos($h, 'x-amz-') === 0 ? $rest->setamzheader($h, $v) : $rest->setheader($h, $v);
                echo "- $h: $v<br>";
            }
        } else if (is_string($requestheaders)) {
            $input['type'] = $requestheaders;
        }

        if (!isset($input['type'])) {
            if (isset($requestheaders['Content-Type'])) {
                $input['type'] =& $requestheaders['Content-Type'];
            } else if (isset($input['file'])) {
                $input['type'] = self::getMIMEType($input['file']);
            } else {
                $input['type'] = 'application/octet-stream';
            }
        }
        $rest->setheader('Content-Type', $input['type']);

        if ($storageclass !== self::STORAGE_CLASS_STANDARD) {
            $rest->setamzheader('x-amz-storage-class', $storageclass);
        }

        if ($serversideencryption !== self::SSE_NONE) {
            $rest->setamzheader('x-amz-server-side-encryption', $serversideencryption);
        }

        if ($rest->size >= 0 && ($rest->fp !== false || $rest->data !== false)) {
            if (isset($input['md5sum'])) {
                $rest->setheader('Content-MD5', $input['md5sum']);
            }
            if (isset($input['sha256sum'])) {
                $rest->setamzheader('x-amz-content-sha256', $input['sha256sum']);
            }

            $rest->setamzheader('x-amz-acl', $acl);
            foreach ($metaheaders as $h => $v) {
                $rest->setamzheader('x-amz-meta-' . $h, $v);
            }

            $rest->getresponse();
        } else {
            $rest->response->error = ['code' => 0, 'message' => 'Missing input parameters'];
            die("ERROR: Missing input parameters. File not uploaded.<br>");
        }

        // Handle errors
        if ($rest->response->error === false && $rest->response->code !== 200) {
            $rest->response->error = [
                    'code' => $rest->response->code,
                    'message' => 'Unexpected HTTP status',
            ];
        }

        if ($rest->response->error !== false) {
            die(sprintf("S3 Upload ERROR: [%s] %s<br>",
                    $rest->response->error['code'], $rest->response->error['message']));
        }

        return true;
    }

    /**
     * Get MIME type for file
     *
     * To override the putObject() Content-Type, add it to $requestHeaders.
     * To use fileinfo, ensure the MAGIC environment variable is set.
     *
     * @param string $file File path (passed by reference)
     * @return string MIME type of the file (defaults to 'application/octet-stream' if type cannot be determined)
     * @internal Used to get mime types
     */
    private static function getmimetype(string $file) {
        static $exts = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
                'png' => 'image/png', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf',
                'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'svg' => 'image/svg+xml',
                'svgz' => 'image/svg+xml', 'swf' => 'application/x-shockwave-flash',
                'zip' => 'application/zip', 'gz' => 'application/x-gzip',
                'tar' => 'application/x-tar', 'bz' => 'application/x-bzip',
                'bz2' => 'application/x-bzip2', 'rar' => 'application/x-rar-compressed',
                'exe' => 'application/x-msdownload', 'msi' => 'application/x-msdownload',
                'cab' => 'application/vnd.ms-cab-compressed', 'txt' => 'text/plain',
                'asc' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html',
                'css' => 'text/css', 'js' => 'text/javascript',
                'xml' => 'text/xml', 'xsl' => 'application/xsl+xml',
                'ogg' => 'application/ogg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav',
                'avi' => 'video/x-msvideo', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
                'mov' => 'video/quicktime', 'flv' => 'video/x-flv', 'php' => 'text/x-php',
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (isset($exts[$ext])) {
            return $exts[$ext];
        }

        // Use fileinfo if available
        if (extension_loaded('fileinfo') && isset($_ENV['MAGIC']) &&
                ($finfo = finfo_open(FILEINFO_MIME, $_ENV['MAGIC'])) !== false) {
            if (($type = finfo_file($finfo, $file)) !== false) {
                // Remove the charset and grab the last content-type
                $type = explode(' ', str_replace('; charset=', ';charset=', $type));
                $type = array_pop($type);
                $type = explode(';', $type);
                $type = trim(array_shift($type));
            }
            finfo_close($finfo);
            if ($type !== false && strlen($type) > 0) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Create input info array for putObject()
     *
     * @param string $file Input file
     * @param mixed $md5sum Use MD5 hash (supply a string if you want to use your own)
     * @return array | false
     */
    public static function inputfile($file, $md5sum = true) {
        if (!file_exists($file)) {
            die("ERROR: File does not exist: $file\n");
        }

        if (!is_file($file)) {
            die("ERROR: Not a valid file: $file\n");
        }

        if (!is_readable($file)) {
            die("ERROR: File is not readable: $file\n");
        }

        clearstatcache(false, $file);

        $filesize = filesize($file);

        return [
                'file' => $file,
                'size' => $filesize,
                'md5sum' => $md5sum !== false ? (is_string($md5sum) ? $md5sum : base64_encode(md5_file($file, true))) : '',
                'sha256sum' => hash_file('sha256', $file),
        ];
    }

    /**
     * Put an object from a string (legacy function)
     *
     * @param string $string Input data
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param constant $acl ACL constant
     * @param array $metaheaders Array of x-amz-meta-* headers
     * @param string $contenttype Content type
     * @return boolean
     */
    public static function putobjectstring($string, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaheaders = [],
            $contenttype = 'text/plain') {
        return self::putobject($string, $bucket, $uri, $acl, $metaheaders, $contenttype);
    }

    /**
     * Get an object
     *
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param mixed $saveto Filename or resource to write to
     * @return mixed
     */
    public static function getobject($bucket, $uri, $saveto = false) {
        $rest = new S3CUSTOMRequest('GET', $bucket, $uri, self::$endpoint);
        if ($saveto !== false) {
            if (is_resource($saveto)) {
                $rest->fp =& $saveto;
            } else if (($rest->fp = @fopen($saveto, 'wb')) !== false) {
                $rest->file = realpath($saveto);
            } else {
                $rest->response->error = ['code' => 0, 'message' => 'Unable to open save file for writing: ' . $saveto];
            }
        }
        if ($rest->response->error === false) {
            $rest->getresponse();
        }

        if ($rest->response->error === false && $rest->response->code !== 200) {
            $rest->response->error = ['code' => $rest->response->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->response->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getobject({$bucket}, {$uri}): [%s] %s",
                    $rest->response->error['code'], $rest->response->error['message']), __FILE__, __LINE__);
            return false;
        }
        return $rest->response;
    }

    /**
     * Get object information
     *
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param boolean $returninfo Return response information
     * @return mixed | false
     */
    public static function getobjectinfo($bucket, $uri, $returninfo = true) {
        $rest = new S3CUSTOMRequest('HEAD', $bucket, $uri, self::$endpoint);
        $rest = $rest->getresponse();
        if ($rest->error === false && ($rest->code !== 200 && $rest->code !== 404)) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getobjectinfo({$bucket}, {$uri}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return $rest->code == 200 ? $returninfo ? $rest->headers : true : false;
    }

    /**
     * Copy an object
     *
     * @param string $srcbucket Source bucket name
     * @param string $srcuri Source object URI
     * @param string $bucket Destination bucket name
     * @param string $uri Destination object URI
     * @param constant $acl ACL constant
     * @param array $metaheaders Optional array of x-amz-meta-* headers
     * @param array $requestheaders Optional array of request headers (content type, disposition, etc.)
     * @param constant $storageclass Storage class constant
     * @return mixed | false
     */
    public static function copyobject($srcbucket, $srcuri, $bucket, $uri, $acl = self::ACL_PRIVATE, $metaheaders = [],
            $requestheaders = [], $storageclass = self::STORAGE_CLASS_STANDARD) {
        $rest = new S3CUSTOMRequest('PUT', $bucket, $uri, self::$endpoint);
        $rest->setheader('Content-Length', 0);
        foreach ($requestheaders as $h => $v) {
            strpos($h, 'x-amz-') === 0 ? $rest->setamzheader($h, $v) : $rest->setheader($h, $v);
        }
        foreach ($metaheaders as $h => $v) {
            $rest->setamzheader('x-amz-meta-' . $h, $v);
        }
        if ($storageclass !== self::STORAGE_CLASS_STANDARD) {
            $rest->setamzheader('x-amz-storage-class', $storageclass);
        }
        $rest->setamzheader('x-amz-acl', $acl);
        $rest->setamzheader('x-amz-copy-source', sprintf('/%s/%s', $srcbucket, rawurlencode($srcuri)));
        if (count($requestheaders) > 0 || count($metaheaders) > 0) {
            $rest->setamzheader('x-amz-metadata-directive', 'REPLACE');
        }

        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::copyobject({$srcbucket}, {$srcuri}, {$bucket}, {$uri}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return isset($rest->body->LastModified, $rest->body->ETag) ? [
                'time' => strtotime((string) $rest->body->LastModified),
                'hash' => substr((string) $rest->body->ETag, 1, -1),
        ] : false;
    }

    /**
     * Set up a bucket redirection
     *
     * @param string $bucket Bucket name
     * @param string $location Target host name
     * @return boolean
     */
    public static function setbucketredirect($bucket = null, $location = null) {
        $rest = new S3CUSTOMRequest('PUT', $bucket, '', self::$endpoint);

        if (empty($bucket) || empty($location)) {
            self::triggererror("simple_storage_service::setbucketredirect({$bucket}, {$location}): Empty parameter.", __FILE__,
                    __LINE__);
            return false;
        }

        $dom = new DOMDocument;
        $websiteconfiguration = $dom->createElement('WebsiteConfiguration');
        $redirectallrequeststo = $dom->createElement('RedirectAllRequestsTo');
        $hostname = $dom->createElement('HostName', $location);
        $redirectallrequeststo->appendChild($hostname);
        $websiteconfiguration->appendChild($redirectallrequeststo);
        $dom->appendChild($websiteconfiguration);
        $rest->setparameter('website', null);
        $rest->data = $dom->saveXML();
        $rest->size = strlen($rest->data);
        $rest->setheader('Content-Type', 'application/xml');
        $rest = $rest->getresponse();

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::setbucketredirect({$bucket}, {$location}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Get logging status for a bucket
     *
     * This will return false if logging is not enabled.
     * Note: To enable logging, you also need to grant write access to the log group
     *
     * @param string $bucket Bucket name
     * @return array | false
     */
    public static function getbucketlogging($bucket) {
        $rest = new S3CUSTOMRequest('GET', $bucket, '', self::$endpoint);
        $rest->setparameter('logging', null);
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getbucketlogging({$bucket}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        if (!isset($rest->body->LoggingEnabled)) {
            return false;
        }
        return [
                'targetbucket' => (string) $rest->body->LoggingEnabled->TargetBucket,
                'targetprefix' => (string) $rest->body->LoggingEnabled->TargetPrefix,
        ];
    }

    /**
     * Disable bucket logging
     *
     * @param string $bucket Bucket name
     * @return boolean
     */
    public static function disablebucketlogging($bucket) {
        return self::setbucketlogging($bucket, null);
    }

    /**
     * Set logging for a bucket
     *
     * @param string $bucket Bucket name
     * @param string $targetbucket Target bucket (where logs are stored)
     * @param string $targetprefix Log prefix (e.g., domain.com-)
     * @return boolean
     */
    public static function setbucketlogging($bucket, $targetbucket, $targetprefix = null) {
        // The S3 log delivery group has to be added to the target bucket's ACP
        if ($targetbucket !== null && ($acp = self::getaccesscontrolpolicy($targetbucket, '')) !== false) {
            // Only add permissions to the target bucket when they do not exist
            $aclwriteset = false;
            $aclreadset = false;
            foreach ($acp['acl'] as $acl) {
                if ($acl['type'] == 'Group' && $acl['uri'] == 'http://acs.amazonaws.com/groups/s3/LogDelivery') {
                    if ($acl['permission'] == 'WRITE') {
                        $aclwriteset = true;
                    } else if ($acl['permission'] == 'READ_ACP') {
                        $aclreadset = true;
                    }
                }
            }
            if (!$aclwriteset) {
                $acp['acl'][] = [
                        'type' => 'Group', 'uri' => 'http://acs.amazonaws.com/groups/s3/LogDelivery', 'permission' => 'WRITE',
                ];
            }
            if (!$aclreadset) {
                $acp['acl'][] = [
                        'type' => 'Group', 'uri' => 'http://acs.amazonaws.com/groups/s3/LogDelivery', 'permission' => 'READ_ACP',
                ];
            }
            if (!$aclreadset || !$aclwriteset) {
                self::setaccesscontrolpolicy($targetbucket, '', $acp);
            }
        }

        $dom = new DOMDocument;
        $bucketloggingstatus = $dom->createElement('BucketLoggingStatus');
        $bucketloggingstatus->setAttribute('xmlns', 'http://s3.amazonaws.com/doc/2006-03-01/');
        if ($targetbucket !== null) {
            if ($targetprefix == null) {
                $targetprefix = $bucket . '-';
            }
            $loggingenabled = $dom->createElement('LoggingEnabled');
            $loggingenabled->appendChild($dom->createElement('TargetBucket', $targetbucket));
            $loggingenabled->appendChild($dom->createElement('TargetPrefix', $targetprefix));
            // TODO: Add TargetGrants?
            $bucketloggingstatus->appendChild($loggingenabled);
        }
        $dom->appendChild($bucketloggingstatus);

        $rest = new S3CUSTOMRequest('PUT', $bucket, '', self::$endpoint);
        $rest->setparameter('logging', null);
        $rest->data = $dom->saveXML();
        $rest->size = strlen($rest->data);
        $rest->setheader('Content-Type', 'application/xml');
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::setbucketlogging({$bucket}, {$targetbucket}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Get object or bucket Access Control Policy
     *
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @return mixed | false
     */
    public static function getaccesscontrolpolicy($bucket, $uri = '') {
        $rest = new S3CUSTOMRequest('GET', $bucket, $uri, self::$endpoint);
        $rest->setparameter('acl', null);
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getaccesscontrolpolicy({$bucket}, {$uri}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }

        $acp = [];
        if (isset($rest->body->Owner, $rest->body->Owner->ID, $rest->body->Owner->DisplayName)) {
            $acp['owner'] = [
                    'id' => (string) $rest->body->Owner->ID, 'name' => (string) $rest->body->Owner->DisplayName,
            ];
        }

        if (isset($rest->body->AccessControlList)) {
            $acp['acl'] = [];
            foreach ($rest->body->AccessControlList->Grant as $grant) {
                foreach ($grant->Grantee as $grantee) {
                    if (isset($grantee->ID, $grantee->DisplayName)) {
                        $acp['acl'][] = [
                                'type' => 'CanonicalUser',
                                'id' => (string) $grantee->ID,
                                'name' => (string) $grantee->DisplayName,
                                'permission' => (string) $grant->Permission,
                        ];
                    } else if (isset($grantee->EmailAddress)) {
                        $acp['acl'][] = [
                                'type' => 'AmazonCustomerByEmail',
                                'email' => (string) $grantee->EmailAddress,
                                'permission' => (string) $grant->Permission,
                        ];
                    } else if (isset($grantee->URI)) {
                        $acp['acl'][] = [
                                'type' => 'Group',
                                'uri' => (string) $grantee->URI,
                                'permission' => (string) $grant->Permission,
                        ];
                    } else {
                        continue;
                    }
                }
            }
        }
        return $acp;
    }

    /**
     * Set object or bucket Access Control Policy
     *
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param array $acp Access Control Policy Data (same as the data returned from getAccessControlPolicy)
     * @return boolean
     */
    public static function setaccesscontrolpolicy($bucket, $uri = '', $acp = []) {
        $dom = new DOMDocument;
        $dom->formatOutput = true;
        $accesscontrolpolicy = $dom->createElement('AccessControlPolicy');
        $accesscontrollist = $dom->createElement('AccessControlList');

        // It seems the owner has to be passed along too
        $owner = $dom->createElement('Owner');
        $owner->appendChild($dom->createElement('ID', $acp['owner']['id']));
        $owner->appendChild($dom->createElement('DisplayName', $acp['owner']['name']));
        $accesscontrolpolicy->appendChild($owner);

        foreach ($acp['acl'] as $g) {
            $grant = $dom->createElement('Grant');
            $grantee = $dom->createElement('Grantee');
            $grantee->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            if (isset($g['id'])) { // CanonicalUser (DisplayName is omitted)
                $grantee->setAttribute('xsi:type', 'CanonicalUser');
                $grantee->appendChild($dom->createElement('ID', $g['id']));
            } else if (isset($g['email'])) { // AmazonCustomerByEmail
                $grantee->setAttribute('xsi:type', 'AmazonCustomerByEmail');
                $grantee->appendChild($dom->createElement('EmailAddress', $g['email']));
            } else if ($g['type'] == 'Group') { // Group
                $grantee->setAttribute('xsi:type', 'Group');
                $grantee->appendChild($dom->createElement('URI', $g['uri']));
            }
            $grant->appendChild($grantee);
            $grant->appendChild($dom->createElement('Permission', $g['permission']));
            $accesscontrollist->appendChild($grant);
        }

        $accesscontrolpolicy->appendChild($accesscontrollist);
        $dom->appendChild($accesscontrolpolicy);

        $rest = new S3CUSTOMRequest('PUT', $bucket, $uri, self::$endpoint);
        $rest->setparameter('acl', null);
        $rest->data = $dom->saveXML();
        $rest->size = strlen($rest->data);
        $rest->setheader('Content-Type', 'application/xml');
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::setaccesscontrolpolicy({$bucket}, {$uri}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Get a bucket's location
     *
     * @param string $bucket Bucket name
     * @return string | false
     */
    public static function getbucketlocation($bucket) {
        $rest = new S3CUSTOMRequest('GET', $bucket, '', self::$endpoint);
        $rest->setparameter('location', null);
        $rest = $rest->getresponse();
        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getbucketlocation({$bucket}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return (isset($rest->body[0]) && (string) $rest->body[0] !== '') ? (string) $rest->body[0] : 'US';
    }

    /**
     * Delete an object
     *
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @return boolean
     */
    public static function deleteobject($bucket, $uri) {
        $rest = new S3CUSTOMRequest('DELETE', $bucket, $uri, self::$endpoint);
        $rest = $rest->getResponse();
        if ($rest->error === false && $rest->code !== 204) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::deleteobject(): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Get a query string authenticated URL
     *
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param integer $lifetime Lifetime in seconds
     * @param boolean $hostbucket Use the bucket name as the hostname
     * @param boolean $https Use HTTPS ($hostbucket should be false for SSL verification)
     * @return string
     */
    public static function getauthenticatedurl($bucket, $uri, $lifetime, $hostbucket = false, $https = false) {
        $expires = self::gettime() + $lifetime;
        $uri = str_replace(['%2F', '%2B'], ['/', '+'], rawurlencode($uri));
        return sprintf(($https ? 'https' : 'http') . '://%s/%s?AWSaccesskeyId=%s&Expires=%u&Signature=%s',
                // $hostbucket ? $bucket : $bucket.'.s3.amazonaws.com', $uri, self::$accesskey, $expires,
                $hostbucket ? $bucket : self::$endpoint . '/' . $bucket, $uri, self::$accesskey, $expires,
                urlencode(self::gethash("GET\n\n\n{$expires}\n/{$bucket}/{$uri}")));
    }

    /**
     * Get the current time
     *
     * @return integer
     * @internal Used to apply offsets to system time
     */
    private static function gettime() {
        return time() + self::$timeoffset;
    }

    /**
     * Creates a HMAC-SHA1 hash
     *
     * This uses the hash extension if loaded
     *
     * @param string $string String to sign
     * @return string
     * @internal Used by getsignature()
     */
    private static function gethash($string) {
        return base64_encode(extension_loaded('hash') ?
                hash_hmac('sha1', $string, self::$secretkey, true) : pack('H*', sha1(
                        (str_pad(self::$secretkey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
                        pack('H*', sha1((str_pad(self::$secretkey, 64, chr(0x00)) ^
                                        (str_repeat(chr(0x36), 64))) . $string)))));
    }

    /**
     * Get a CloudFront canned policy URL
     *
     * @param string $url URL to sign
     * @param integer $lifetime URL lifetime
     * @return string
     */
    public static function getsignedcannedurl($url, $lifetime) {
        return self::getsignedpolicyurl([
                'Statement' => [
                        ['Resource' => $url, 'Condition' => [
                                'DateLessThan' => ['AWS:EpochTime' => self::gettime() + $lifetime],
                        ]],
                ],
        ]);
    }

    /**
     * Get a CloudFront signed policy URL
     *
     * @param array $policy Policy
     * @return string
     */
    public static function getsignedpolicyurl($policy) {
        $data = json_encode($policy);
        $signature = '';
        if (!openssl_sign($data, $signature, self::$signingkeyresource)) {
            return false;
        }

        $encoded = str_replace(['+', '='], ['-', '_'], base64_encode($data));
        $signature = str_replace(['+', '='], ['-', '_'], base64_encode($signature));

        $url = $policy['Statement'][0]['Resource'] . '?';
        foreach (['Policy' => $encoded, 'Signature' => $signature, 'Key-Pair-Id' => self::$signingkeypairid] as $k => $v) {
            $url .= $k . '=' . str_replace('%2F', '/', rawurlencode($v)) . '&';
        }
        return substr($url, 0, -1);
    }

    /**
     * Get upload POST parameters for form uploads.
     *
     * This function generates the necessary parameters for uploading files to S3 using a POST form.
     * It creates a policy object with conditions and encodes it to be used in the form.
     *
     * @param string $bucket Bucket name
     * @param string $uriprefix Object URI prefix
     * @param string $acl ACL constant
     * @param integer $lifetime Lifetime in seconds
     * @param integer $maxfilesize Maximum filesize in bytes (default 5MB)
     * @param string $successredirect Redirect URL or 200 / 201 status code
     * @param array $amzheaders Array of x-amz-meta-* headers
     * @param array $headers Array of request headers or content type as a string
     * @param boolean $flashvars Includes additional "Filename" variable posted by Flash
     * @return object An object containing the POST parameters
     */
    public static function gethttpuploadpostparams($bucket, $uriprefix = '', $acl = self::ACL_PRIVATE, $lifetime = 3600,
            $maxfilesize = 5242880, $successredirect = "201", $amzheaders = [], $headers = [], $flashvars = false) {
        // Create policy object
        $policy = new stdClass;
        $policy->expiration = gmdate('Y-m-d\TH:i:s\Z', (self::gettime() + $lifetime));
        $policy->conditions = [];
        $obj = new stdClass;
        $obj->bucket = $bucket;
        array_push($policy->conditions, $obj);
        $obj = new stdClass;
        $obj->acl = $acl;
        array_push($policy->conditions, $obj);

        $obj = new stdClass; // 200 for non-redirect uploads
        if (is_numeric($successredirect) && in_array((int) $successredirect, [200, 201])) {
            $obj->success_action_status = (string) $successredirect;
        } else {
            $obj->success_action_redirect = $successredirect;
        }
        array_push($policy->conditions, $obj);

        if ($acl !== self::ACL_PUBLIC_READ) {
            array_push($policy->conditions, ['eq', '$acl', $acl]);
        }

        array_push($policy->conditions, ['starts-with', '$key', $uriprefix]);
        if ($flashvars) {
            array_push($policy->conditions, ['starts-with', '$Filename', '']);
        }
        foreach (array_keys($headers) as $headerkey) {
            array_push($policy->conditions, ['starts-with', '$' . $headerkey, '']);
        }
        foreach ($amzheaders as $headerkey => $headerval) {
            $obj = new stdClass;
            $obj->{$headerkey} = (string) $headerval;
            array_push($policy->conditions, $obj);
        }
        array_push($policy->conditions, ['content-length-range', 0, $maxfilesize]);
        $policy = base64_encode(str_replace('\/', '/', json_encode($policy)));

        // Create parameters
        $params = new stdClass;
        $params->AWSaccesskeyId = self::$accesskey;
        $params->key = $uriprefix . '${filename}';
        $params->acl = $acl;
        $params->policy = $policy;
        unset($policy);
        $params->signature = self::gethash($params->policy);
        if (is_numeric($successredirect) && in_array((int) $successredirect, [200, 201])) {
            $params->success_action_status = (string) $successredirect;
        } else {
            $params->success_action_redirect = $successredirect;
        }
        foreach ($headers as $headerkey => $headerval) {
            $params->{$headerkey} = (string) $headerval;
        }
        foreach ($amzheaders as $headerkey => $headerval) {
            $params->{$headerkey} = (string) $headerval;
        }
        return $params;
    }

    /**
     * Create a CloudFront distribution
     *
     * @param string $bucket Bucket name
     * @param boolean $enabled Enabled (true/false)
     * @param array $cnames Array containing CNAME aliases
     * @param string $comment Use the bucket name as the hostname
     * @param string $defaultrootobject Default root object
     * @param string $originaccessidentity Origin access identity
     * @param array $trustedsigners Array of trusted signers
     * @return array | false
     */
    public static function createdistribution($bucket, $enabled = true, $cnames = [], $comment = null, $defaultrootobject = null,
            $originaccessidentity = null, $trustedsigners = []) {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::createdistribution({$bucket}, " . (int) $enabled .
                    ", [], '$comment'): %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }
        $usessl = self::$usessl;

        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('POST', '', '2010-11-01/distribution', 'cloudfront.amazonaws.com');
        $rest->data = self::getcloudfrontdistributionconfigxml(
                $bucket . '.s3.amazonaws.com',
                $enabled,
                (string) $comment,
                (string) microtime(true),
                $cnames,
                $defaultrootobject,
                $originaccessidentity,
                $trustedsigners
        );

        $rest->size = strlen($rest->data);
        $rest->setheader('Content-Type', 'application/xml');
        $rest = self::getcloudfrontresponse($rest);

        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 201) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::createdistribution({$bucket}, " . (int) $enabled .
                    ", [], '$comment'): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        } else if ($rest->body instanceof SimpleXMLElement) {
            return self::__parsecloudfrontdistributionconfig($rest->body);
        }
        return false;
    }

    /**
     * Get a DistributionConfig DOMDocument
     *
     * http://docs.amazonwebservices.com/AmazonCloudFront/latest/APIReference/index.html?PutConfig.html
     *
     * @param string $bucket S3 Origin bucket
     * @param boolean $enabled Enabled (true/false)
     * @param string $comment Comment to append
     * @param string $callerreference Caller reference
     * @param array $cnames Array of CNAME aliases
     * @param string $defaultrootobject Default root object
     * @param string $originaccessidentity Origin access identity
     * @param array $trustedsigners Array of trusted signers
     * @return string
     * @internal Used to create XML in createDistribution() and updateDistribution()
     */
    private static function getcloudfrontdistributionconfigxml($bucket, $enabled, $comment, $callerreference = '0', $cnames = [],
            $defaultrootobject = null, $originaccessidentity = null, $trustedsigners = []) {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $distributionconfig = $dom->createElement('DistributionConfig');
        $distributionconfig->setAttribute('xmlns', 'http://cloudfront.amazonaws.com/doc/2010-11-01/');

        $origin = $dom->createElement('S3Origin');
        $origin->appendChild($dom->createElement('DNSName', $bucket));
        if ($originaccessidentity !== null) {
            $origin->appendChild($dom->createElement('OriginAccessIdentity', $originaccessidentity));
        }
        $distributionconfig->appendChild($origin);

        if ($defaultrootobject !== null) {
            $distributionconfig->appendChild($dom->createElement('DefaultRootObject', $defaultrootobject));
        }

        $distributionconfig->appendChild($dom->createElement('CallerReference', $callerreference));
        $distributionconfig->appendChild($dom->createElement('Comment', $comment));
        $distributionconfig->appendChild($dom->createElement('Enabled', $enabled ? 'true' : 'false'));

        if (count($cnames) > 0) {
            $aliases = $dom->createElement('CNAME');
            foreach ($cnames as $cname) {
                $aliases->appendChild($dom->createElement('CNAME', $cname));
            }
            $distributionconfig->appendChild($aliases);
        }

        if (count($trustedsigners) > 0) {
            $signers = $dom->createElement('TrustedSigners');
            $signers->appendChild($dom->createElement('Self', 'true'));
            foreach ($trustedsigners as $signer) {
                $signers->appendChild($dom->createElement('AwsAccountNumber', $signer));
            }
            $distributionconfig->appendChild($signers);
        }
        $dom->appendChild($distributionconfig);
        return $dom->saveXML();
    }

    /**
     * Process the CloudFront response.
     *
     * This function processes the response from a CloudFront request, checking for errors and parsing the XML body.
     *
     * @param S3CUSTOMRequest $rest The S3CUSTOMRequest object containing the response to process
     * @return stdClass|false The processed response object with errors parsed if present, false on failure
     */
    private static function getcloudfrontresponse(S3CUSTOMRequest $rest) {
        $rest->getresponse();
        if ($rest->response->error === false && isset($rest->response->body) &&
                is_string($rest->response->body) && substr($rest->response->body, 0, 5) == '<?xml') {
            $rest->response->body = simplexml_load_string($rest->response->body);
            // Grab CloudFront errors
            if (isset($rest->response->body->Error, $rest->response->body->Error->Code,
                    $rest->response->body->Error->Message)) {
                $rest->response->error = [
                        'code' => (string) $rest->response->body->Error->Code,
                        'message' => (string) $rest->response->body->Error->Message,
                ];
                unset($rest->response->body);
            }
        }
        return $rest->response;
    }

    /**
     * Get CloudFront distribution info
     *
     * @param string $distributionid Distribution ID from listDistributions()
     * @return array | false
     */
    public static function getdistribution($distributionid) {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::getdistribution($distributionid): %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }
        $usessl = self::$usessl;

        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('GET', '', '2010-11-01/distribution/' . $distributionid, 'cloudfront.amazonaws.com');
        $rest = self::getcloudfrontresponse($rest);

        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::getdistribution($distributionid): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        } else if ($rest->body instanceof SimpleXMLElement) {
            $dist = self::__parsecloudfrontdistributionconfig($rest->body);
            $dist['hash'] = $rest->headers['hash'];
            $dist['id'] = $distributionid;
            return $dist;
        }
        return false;
    }

    /**
     * Update a CloudFront distribution
     *
     * @param array $dist Distribution array info identical to output of getDistribution()
     * @return array | false
     */
    public static function updatedistribution($dist) {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::updatedistribution({$dist['id']}): %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }

        $usessl = self::$usessl;

        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('PUT', '', '2010-11-01/distribution/' . $dist['id'] . '/config', 'cloudfront.amazonaws.com');
        $rest->data = self::getcloudfrontdistributionconfigxml(
                $dist['origin'],
                $dist['enabled'],
                $dist['comment'],
                $dist['callerreference'],
                $dist['cnames'],
                $dist['defaultrootobject'],
                $dist['originaccessidentity'],
                $dist['trustedsigners']
        );

        $rest->size = strlen($rest->data);
        $rest->setheader('If-Match', $dist['hash']);
        $rest = self::getcloudfrontresponse($rest);

        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::updatedistribution({$dist['id']}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        } else {
            $dist = self::__parsecloudfrontdistributionconfig($rest->body);
            $dist['hash'] = $rest->headers['hash'];
            return $dist;
        }
        return false;
    }

    /**
     * Delete a CloudFront distribution
     *
     * @param array $dist Distribution array info identical to output of getDistribution()
     * @return boolean
     */
    public static function deletedistribution($dist) {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::deletedistribution({$dist['id']}): %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }

        $usessl = self::$usessl;

        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('DELETE', '', '2008-06-30/distribution/' . $dist['id'], 'cloudfront.amazonaws.com');
        $rest->setheader('If-Match', $dist['hash']);
        $rest = self::getcloudfrontresponse($rest);

        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 204) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::deletedistribution({$dist['id']}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        }
        return true;
    }

    /**
     * Get a list of CloudFront distributions
     *
     * @return array
     */
    public static function listdistributions() {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::listdistributions(): [%s] %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }

        $usessl = self::$usessl;
        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('GET', '', '2010-11-01/distribution', 'cloudfront.amazonaws.com');
        $rest = self::getcloudfrontresponse($rest);
        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            self::triggererror(sprintf("simple_storage_service::listdistributions(): [%s] %s",
                    $rest->error['code'], $rest->error['message']), __FILE__, __LINE__);
            return false;
        } else if ($rest->body instanceof SimpleXMLElement && isset($rest->body->DistributionSummary)) {
            $list = [];
            foreach ($rest->body->DistributionSummary as $summary) {
                $list[(string) $summary->Id] = self::__parsecloudfrontdistributionconfig($summary);
            }

            return $list;
        }
        return [];
    }

    /**
     * List CloudFront Origin Access Identities
     *
     * @return array
     */
    public static function listoriginaccessidentities() {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::listoriginaccessidentities(): [%s] %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }

        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('GET', '', '2010-11-01/origin-access-identity/cloudfront', 'cloudfront.amazonaws.com');
        $rest = self::getcloudfrontresponse($rest);
        $usessl = self::$usessl;

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            trigger_error(sprintf("simple_storage_service::listoriginaccessidentities(): [%s] %s",
                    $rest->error['code'], $rest->error['message']), E_USER_WARNING);
            return false;
        }

        if (isset($rest->body->CloudFrontOriginAccessIdentitySummary)) {
            $identities = [];
            foreach ($rest->body->CloudFrontOriginAccessIdentitySummary as $identity) {
                if (isset($identity->S3CanonicalUserId)) {
                    $identities[(string) $identity->Id] =
                            ['id' => (string) $identity->Id, 's3canonicaluserid' => (string) $identity->S3CanonicalUserId];
                }
            }
            return $identities;
        }
        return false;
    }

    /**
     * Invalidate objects in a CloudFront distribution
     *
     * Thanks to Martin Lindkvist for simple_storage_service::invalidateDistribution()
     *
     * @param string $distributionid Distribution ID from listDistributions()
     * @param array $paths Array of object paths to invalidate
     * @return boolean
     */
    public static function invalidatedistribution($distributionid, $paths) {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::invalidatedistribution(): [%s] %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }

        $usessl = self::$usessl;
        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('POST', '', '2010-08-01/distribution/' . $distributionid . '/invalidation',
                'cloudfront.amazonaws.com');
        $rest->data = self::getcloudfrontinvalidationbatchxml($paths, (string) microtime(true));
        $rest->size = strlen($rest->data);
        $rest = self::getcloudfrontresponse($rest);
        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 201) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            trigger_error(sprintf("simple_storage_service::invalidate('{$distributionid}',{$paths}): [%s] %s",
                    $rest->error['code'], $rest->error['message']), E_USER_WARNING);
            return false;
        }
        return true;
    }

    /**
     * Get a InvalidationBatch DOMDocument
     *
     * @param array $paths Paths to objects to invalidateDistribution
     * @param int $callerreference
     * @return string
     * @internal Used to create XML in invalidateDistribution()
     */
    private static function getcloudfrontinvalidationbatchxml($paths, $callerreference = '0') {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $invalidationbatch = $dom->createElement('InvalidationBatch');
        foreach ($paths as $path) {
            $invalidationbatch->appendChild($dom->createElement('Path', $path));
        }
        $invalidationbatch->appendChild($dom->createElement('CallerReference', $callerreference));
        $dom->appendChild($invalidationbatch);
        return $dom->saveXML();
    }

    /**
     * List your invalidation batches for invalidateDistribution() in a CloudFront distribution
     *
     * http://docs.amazonwebservices.com/AmazonCloudFront/latest/APIReference/ListInvalidation.html
     * returned array looks like this:
     *    Array
     *    (
     *        [I31TWB0CN9V6XD] => InProgress
     *        [IT3TFE31M0IHZ] => Completed
     *        [I12HK7MPO1UQDA] => Completed
     *        [I1IA7R6JKTC3L2] => Completed
     *    )
     *
     * @param string $distributionid Distribution ID from listDistributions()
     * @return array
     */
    public static function getdistributioninvalidationlist($distributionid) {
        if (!extension_loaded('openssl')) {
            self::triggererror(sprintf("simple_storage_service::getdistributioninvalidationlist(): [%s] %s",
                    "CloudFront functionality requires SSL"), __FILE__, __LINE__);
            return false;
        }

        $usessl = self::$usessl;
        self::$usessl = true; // CloudFront requires SSL
        $rest = new S3CUSTOMRequest('GET', '', '2010-11-01/distribution/' . $distributionid . '/invalidation',
                'cloudfront.amazonaws.com');
        $rest = self::getcloudfrontresponse($rest);
        self::$usessl = $usessl;

        if ($rest->error === false && $rest->code !== 200) {
            $rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
        }
        if ($rest->error !== false) {
            trigger_error(sprintf("simple_storage_service::getdistributioninvalidationlist('{$distributionid}'): [%s]",
                    $rest->error['code'], $rest->error['message']), E_USER_WARNING);
            return false;
        } else if ($rest->body instanceof SimpleXMLElement && isset($rest->body->InvalidationSummary)) {
            $list = [];
            foreach ($rest->body->InvalidationSummary as $summary) {
                $list[(string) $summary->Id] = (string) $summary->Status;
            }

            return $list;
        }
        return [];
    }

    /**
     * Generate the auth string: "AWS accesskey:Signature"
     *
     * @param string $string String to sign
     * @return string
     * @internal Used by S3CUSTOMRequest::getResponse()
     */
    public static function getsignature($string) {
        return 'AWS ' . self::$accesskey . ':' . self::gethash($string);
    }

    /**
     * Generate the headers for AWS Signature V4
     *
     * @param array $aheaders amzHeaders
     * @param array $headers
     * @param string $method
     * @param string $uri
     * @param string $data
     * @return array $headers
     * @internal Used by S3CUSTOMRequest::getResponse()
     */
    public static function getsignaturev4($aheaders, $headers, $method = 'GET', $uri = '', $data = '') {
        $service = 's3';
        $region = self::getregion();

        $algorithm = 'AWS4-HMAC-SHA256';
        $amzheaders = [];
        $amzrequests = [];
        $amzdate = gmdate('Ymd\THis\Z');
        $amzdatestamp = gmdate('Ymd');

        foreach ($headers as $k => $v) {
            $amzheaders[strtolower($k)] = trim($v);
        }
        foreach ($aheaders as $k => $v) {
            $amzheaders[strtolower($k)] = trim($v);
        }

        $amzheaders['x-amz-date'] = $amzdate;
        uksort($amzheaders, 'strcmp');
        $payloadhash = isset($amzheaders['x-amz-content-sha256']) ? $amzheaders['x-amz-content-sha256'] : hash('sha256', $data);
        $parameters = [];
        if (strpos($uri, '?')) {
            [$uri, $querystr] = explode("?", $uri, 2);
            parse_str($querystr, $parameters);
        }
        $amzrequests[] = $method;
        $amzrequests[] = $uri;
        $amzrequests[] = http_build_query($parameters);
        foreach ($amzheaders as $k => $v) {
            $amzrequests[] = $k . ':' . $v;
        }
        $amzrequests[] = ''; // Blank line
        $amzrequests[] = implode(';', array_keys($amzheaders)); // Signed headers
        $amzrequests[] = $payloadhash; // Payload hash
        $amzrequeststr = implode("\n", $amzrequests);
        $credentialscope = "$amzdatestamp/$region/$service/aws4_request";
        $stringtosign = "$algorithm\n$amzdate\n$credentialscope\n" . hash('sha256', $amzrequeststr);
        $ksecret = 'AWS4' . self::$secretkey;
        $kdate = hash_hmac('sha256', $amzdatestamp, $ksecret, true);
        $kregion = hash_hmac('sha256', $region, $kdate, true);
        $kservice = hash_hmac('sha256', $service, $kregion, true);
        $ksigning = hash_hmac('sha256', 'aws4_request', $kservice, true);
        $signature = hash_hmac('sha256', $stringtosign, $ksigning);
        $authorization = "$algorithm Credential=" . self::$accesskey . "/$credentialscope, SignedHeaders=" .
                implode(';', array_keys($amzheaders)) . ", Signature=$signature";
        $amzheaders['Authorization'] = $authorization;
        return $amzheaders;
    }

    /**
     * Set the service endpoint
     *
     * @param string $host Hostname
     * @return void
     */
    public function setendpoint($host) {
        self::$endpoint = $host;
    }

    /**
     * Set the service region
     *
     * @param string $region
     * @return void
     */
    public function setregion($region) {
        self::$region = $region;
    }
}

/**
 * Class S3CUSTOMRequest
 *
 * This class handles the construction and execution of HTTP requests to the Amazon S3 service.
 * It manages the request headers, method, and data, and processes the response from the server.
 *
 * @package block_teamsonline
 */
final class S3CUSTOMRequest {
    /**
     * Use HTTP PUT?
     *
     * @var bool
     * @access public
     */
    public $fp = false;
    /**
     * PUT file size
     *
     * @var int
     * @access public
     */
    public $size = 0;
    /**
     * PUT post fields
     *
     * @var array
     * @access public
     */
    public $data = false;
    /**
     * S3 request response
     *
     * @var object
     * @access public
     */
    public $response;
    /**
     * AWS URI
     *
     * @var string
     * @access private
     */
    private $endpoint;
    /**
     * Verb
     *
     * @var string
     * @access private
     */
    private $verb;
    /**
     * S3 bucket name
     *
     * @var string
     * @access private
     */
    private $bucket;
    /**
     * Object URI
     *
     * @var string
     * @access private
     */
    private $uri;
    /**
     * Final object URI
     *
     * @var string
     * @access private
     */
    private $resource = '';
    /**
     * Additional request parameters
     *
     * @var array
     * @access private
     */
    private $parameters = [];
    /**
     * Amazon specific request headers
     *
     * @var array
     * @access private
     */
    private $amzheaders = [];
    /**
     * HTTP request headers
     *
     * @var array
     * @access private
     */
    private $headers = [
            'Host' => '', 'Date' => '', 'Content-MD5' => '', 'Content-Type' => '',
    ];

    /**
     * Constructor
     *
     * @param string $verb Verb
     * @param string $bucket Bucket name
     * @param string $uri Object URI
     * @param string $endpoint AWS endpoint URI
     * @return mixed
     */
    public function __construct($verb, $bucket = '', $uri = '', $endpoint = 's3.amazonaws.com') {

        $this->endpoint = $endpoint;
        $this->verb = $verb;
        $this->bucket = $bucket;
        $this->uri = $uri !== '' ? '/' . str_replace('%2F', '/', rawurlencode($uri)) : '/';

        if ($this->bucket !== '') {
            if ($this->dnsbucketname($this->bucket)) {
                $this->headers['Host'] = $this->bucket . '.' . $this->endpoint;
                $this->resource = '/' . $this->bucket . $this->uri;
            } else {
                $this->headers['Host'] = $this->endpoint;
                $this->uri = $this->uri;
                if ($this->bucket !== '') {
                    $this->uri = '/' . $this->bucket . $this->uri;
                }
                $this->bucket = '';
                $this->resource = $this->uri;
            }
        } else {
            $this->headers['Host'] = $this->endpoint;
            $this->resource = $this->uri;
        }

        $this->headers['Date'] = gmdate('D, d M Y H:i:s T');
        $this->response = new stdClass;
        $this->response->error = false;
        $this->response->body = null;
        $this->response->headers = [];
    }

    /**
     * Check DNS conformity
     *
     * @param string $bucket Bucket name
     * @return boolean
     */
    private function dnsbucketname($bucket) {
        if (strlen($bucket) > 63 || preg_match("/[^a-z0-9\.-]/", $bucket) > 0) {
            return false;
        }
        if (simple_storage_service::$usessl && strstr($bucket, '.') !== false) {
            return false;
        }
        if (strstr($bucket, '-.') !== false) {
            return false;
        }
        if (strstr($bucket, '..') !== false) {
            return false;
        }
        if (!preg_match("/^[0-9a-z]/", $bucket)) {
            return false;
        }
        if (!preg_match("/[0-9a-z]$/", $bucket)) {
            return false;
        }
        return true;
    }

    /**
     * Set request parameter
     *
     * @param string $key Key
     * @param string $value Value
     * @return void
     */
    public function setparameter($key, $value) {
        $this->parameters[$key] = $value;
    }

    /**
     * Set request header
     *
     * @param string $key Key
     * @param string $value Value
     * @return void
     */
    public function setheader($key, $value) {
        $this->headers[$key] = $value;
    }

    /**
     * Set x-amz-meta-* header
     *
     * @param string $key Key
     * @param string $value Value
     * @return void
     */
    public function setamzheader($key, $value) {
        $this->amzheaders[$key] = $value;
    }

    /**
     * Get the S3 response
     *
     * @return object | false
     */
    public function getresponse() {
        $query = '';
        if (count($this->parameters) > 0) {
            $query = substr($this->uri, -1) !== '?' ? '?' : '&';
            foreach ($this->parameters as $var => $value) {
                if ($value == null || $value == '') {
                    $query .= $var . '&';
                } else {
                    $query .= $var . '=' . rawurlencode($value) . '&';
                }
            }
            $query = substr($query, 0, -1);
            $this->uri .= $query;

            if (array_key_exists('acl', $this->parameters) ||
                    array_key_exists('location', $this->parameters) ||
                    array_key_exists('torrent', $this->parameters) ||
                    array_key_exists('website', $this->parameters) ||
                    array_key_exists('logging', $this->parameters)) {
                $this->resource .= $query;
            }
        }

        $url = (simple_storage_service::$usessl ? 'https://' : 'http://') .
                ($this->headers['Host'] !== '' ? $this->headers['Host'] : $this->endpoint) .
                $this->uri;

        // Basic setup
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, 'S3/php');

        if (simple_storage_service::$usessl) {
            curl_setopt($curl, CURLOPT_SSLVERSION, simple_storage_service::$usesslversion);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, simple_storage_service::$usesslvalidation ? 2 : 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, simple_storage_service::$usesslvalidation ? 1 : 0);

            if (simple_storage_service::$sslkey !== null) {
                curl_setopt($curl, CURLOPT_SSLKEY, simple_storage_service::$sslkey);
            }
            if (simple_storage_service::$sslcert !== null) {
                curl_setopt($curl, CURLOPT_SSLCERT, simple_storage_service::$sslcert);
            }
            if (simple_storage_service::$sslcacert !== null) {
                curl_setopt($curl, CURLOPT_CAINFO, simple_storage_service::$sslcacert);
            }
        }

        curl_setopt($curl, CURLOPT_URL, $url);

        if (simple_storage_service::hasauth()) {
            if ($this->headers['Host'] == 'cloudfront.amazonaws.com') {
                $headers[] = 'Authorization: ' . simple_storage_service::getsignature($this->headers['Date']);
            } else {
                if (simple_storage_service::$signver == 'v2') {
                    $headers[] = 'Authorization: ' . simple_storage_service::getsignature(
                                    $this->verb . "\n" .
                                    $this->headers['Content-MD5'] . "\n" .
                                    $this->headers['Content-Type'] . "\n" .
                                    $this->headers['Date'] . $amz . "\n" .
                                    $this->resource
                            );
                } else {
                    $amzheaders = simple_storage_service::getsignaturev4(
                            $this->amzheaders,
                            $this->headers,
                            $this->verb,
                            $this->uri,
                            $this->data
                    );

                    foreach ($amzheaders as $k => $v) {
                        $headers[] = $k . ': ' . $v;
                    }
                }
            }
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, [&$this, 'responsewritecallback']);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, [&$this, 'responseheadercallback']);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        // Request types
        switch ($this->verb) {
            case 'GET':
                break;
            case 'PUT':
            case 'POST':
                if ($this->fp !== false) {
                    curl_setopt($curl, CURLOPT_PUT, true);
                    curl_setopt($curl, CURLOPT_INFILE, $this->fp);
                    if ($this->size >= 0) {
                        curl_setopt($curl, CURLOPT_INFILESIZE, $this->size);
                    }
                } else if ($this->data !== false) {
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
                } else {
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
                }
                break;
            case 'HEAD':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
                curl_setopt($curl, CURLOPT_NOBODY, true);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        // Execute the request
        if (curl_exec($curl)) {
            $this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } else {
            $this->response->error = [
                    'code' => curl_errno($curl),
                    'message' => curl_error($curl),
                    'resource' => $this->resource,
            ];
        }

        @curl_close($curl);

        // Check for AWS errors
        if ($this->response->error === false && isset($this->response->headers['type']) &&
                $this->response->headers['type'] == 'application/xml' && isset($this->response->body)) {
            $this->response->body = simplexml_load_string($this->response->body);

            if (!in_array($this->response->code, [200, 204, 206]) &&
                    isset($this->response->body->Code, $this->response->body->Message)) {
                $this->response->error = [
                        'code' => (string) $this->response->body->Code,
                        'message' => (string) $this->response->body->Message,
                ];
                if (isset($this->response->body->Resource)) {
                    $this->response->error['resource'] = (string) $this->response->body->Resource;
                }
                unset($this->response->body);

            }
        }

        return $this->response;
    }

    /**
     * Sort compare for meta headers
     *
     * @param string $a String A
     * @param string $b String B
     * @return integer
     * @internal Used to sort x-amz meta headers
     */
    private function sortmetaheaderscmp($a, $b) {
        $lena = strpos($a, ':');
        $lenb = strpos($b, ':');
        $minlen = min($lena, $lenb);
        $ncmp = strncmp($a, $b, $minlen);
        if ($lena == $lenb) {
            return $ncmp;
        }
        if (0 == $ncmp) {
            return $lena < $lenb ? -1 : 1;
        }
        return $ncmp;
    }

    /**
     * CURL write callback
     *
     * Called by CURL for each chunk of data received.
     *
     * @param resource $curl CURL resource handle
     * @param string $data Data chunk to write
     * @return int|false Number of bytes written, or false on failure
     */
    private function responsewritecallback($curl, string $data) {
        if (in_array($this->response->code, [200, 206]) && $this->fp !== false) {
            return fwrite($this->fp, $data);
        } else {
            $this->response->body .= $data;
        }
        return strlen($data);
    }

    /**
     * CURL header callback
     *
     * @param resource $curl CURL resource
     * @param string $data Data
     * @return integer
     */
    private function responseheadercallback($curl, $data) {
        if (($strlen = strlen($data)) <= 2) {
            return $strlen;
        }
        if (substr($data, 0, 4) == 'HTTP') {
            $this->response->code = (int) substr($data, 9, 3);
        } else {
            $data = trim($data);
            if (strpos($data, ': ') === false) {
                return $strlen;
            }
            [$header, $value] = explode(': ', $data, 2);
            if ($header == 'Last-Modified') {
                $this->response->headers['time'] = strtotime($value);
            } else if ($header == 'Date') {
                $this->response->headers['date'] = strtotime($value);
            } else if ($header == 'Content-Length') {
                $this->response->headers['size'] = (int) $value;
            } else if ($header == 'Content-Type') {
                $this->response->headers['type'] = $value;
            } else if ($header == 'ETag') {
                $this->response->headers['hash'] = $value[0] == '"' ? substr($value, 1, -1) : $value;
            } else if (preg_match('/^x-amz-meta-.*$/', $header)) {
                $this->response->headers[$header] = $value;
            }
        }
        return $strlen;
    }
}

/**
 * S3 exception class
 *
 * @link http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 * @version 0.5.0-dev
 */
class S3CustomException extends Exception {
    /**
     * Class constructor
     *
     * @param string $message Exception message
     * @param string $file File in which exception was created
     * @param string $line Line number on which exception was created
     * @param int $code Exception code
     */
    public function __construct($message, $file, $line, $code = 0) {
        parent::__construct($message, $code);
        $this->file = $file;
        $this->line = $line;
    }
}
