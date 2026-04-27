<?php
/*
 * @Author: witersen
 * 
 * @LastEditors: witersen
 * 
 * @Description: QQ:1801168257
 */

namespace app\service;

use stdClass;

class Ldap extends Base
{
    function __construct($parm = [])
    {
        parent::__construct($parm);
    }

    /**
     * 输入一个字符串 确保不以 UTF-8 输出
     *
     * @param string $input_string
     * @return string
     */
    private function ensure_not_utf8($input_string)
    {
        if (mb_detect_encoding($input_string, 'UTF-8', true) === 'UTF-8') {
            return mb_convert_encoding($input_string, 'ISO-8859-1', 'UTF-8');
        }
        return $input_string;
    }

    /**
     * 输入一个字符串 确保以 UTF-8 输出
     *
     * @param string $input_string
     * @return string
     */
    private function ensure_utf8($input_string)
    {
        if (mb_detect_encoding($input_string, 'UTF-8', true) !== 'UTF-8') {
            return mb_convert_encoding($input_string, 'UTF-8', 'auto');
        }
        return $input_string;
    }

    /**
     * 对将要发送到 LDAP 服务器的所有DN和属性进行编码处理
     *
     * @param string $str
     *
     * @return string
     */
    private function prepareQueryString($str, $protocolVersion)
    {
        if ($protocolVersion >= 3) {
            $str = $this->ensure_utf8($str);
        } elseif ($protocolVersion <= 2) {
            $str = $this->ensure_not_utf8($str);
        }
        return $str;
    }

    /**
     * 处理从 LDAP 服务器接收的字符串
     *
     * @param string $str
     *
     * @return string
     */
    private function prepareResultString($str, $protocolVersion)
    {
        if ($protocolVersion >= 3) {
            $str = $this->ensure_utf8($str);
        } elseif ($protocolVersion <= 2) {
            $str = $this->ensure_utf8($str);
        }
        return $str;
    }

    private function splitAttributes($attributes)
    {
        if (is_array($attributes)) {
            $result = $attributes;
        } else {
            $result = explode(',', (string)$attributes);
        }

        $result = array_map('trim', $result);
        return array_values(array_filter($result, function ($value) {
            return $value !== '';
        }));
    }

    private function appendAttribute(&$attributes, $attribute)
    {
        $attribute = trim((string)$attribute);
        if ($attribute === '' || strtolower($attribute) == 'dn') {
            return;
        }

        foreach ($attributes as $item) {
            if (strtolower($item) == strtolower($attribute)) {
                return;
            }
        }

        $attributes[] = $attribute;
    }

    private function getLdapAttributeValue($object, $attribute, $default = '')
    {
        $attribute = trim((string)$attribute);
        if ($attribute === '') {
            return $default;
        }

        if (strtolower($attribute) == 'dn') {
            return property_exists($object, 'dn') ? $object->dn : $default;
        }

        $candidate = null;
        if (property_exists($object, $attribute)) {
            $candidate = $attribute;
        } elseif (property_exists($object, strtolower($attribute))) {
            $candidate = strtolower($attribute);
        } else {
            foreach (get_object_vars($object) as $key => $value) {
                if (strtolower($key) == strtolower($attribute)) {
                    $candidate = $key;
                    break;
                }
            }
        }

        if ($candidate === null) {
            return $default;
        }

        $value = $object->$candidate;
        if (is_array($value)) {
            return empty($value) ? $default : (string)$value[0];
        }

        return (string)$value;
    }

    private function getLdapAttributeValues($object, $attribute)
    {
        $attribute = trim((string)$attribute);
        if ($attribute === '') {
            return [];
        }

        if (strtolower($attribute) == 'dn') {
            return property_exists($object, 'dn') && $object->dn !== '' ? [$object->dn] : [];
        }

        $candidate = null;
        if (property_exists($object, $attribute)) {
            $candidate = $attribute;
        } elseif (property_exists($object, strtolower($attribute))) {
            $candidate = strtolower($attribute);
        } else {
            foreach (get_object_vars($object) as $key => $value) {
                if (strtolower($key) == strtolower($attribute)) {
                    $candidate = $key;
                    break;
                }
            }
        }

        if ($candidate === null) {
            return [];
        }

        $value = $object->$candidate;
        if (is_array($value)) {
            $values = $value;
        } else {
            $values = [$value];
        }

        $values = array_map(function ($item) {
            return trim((string)$item);
        }, $values);

        return array_values(array_filter($values, function ($item) {
            return $item !== '';
        }));
    }

    private function normalizeLdapMemberValue($value)
    {
        return strtolower(trim((string)$value));
    }

    private function configBool($value, $default = false)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($value, ['false', '0', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return $default;
    }

    private function buildLdapUserDetails($ldapUsers, $dataSource, $usernameAttribute)
    {
        $details = [];
        foreach ($ldapUsers as $ldapUser) {
            $username = $this->getLdapAttributeValue($ldapUser, $usernameAttribute);
            if ($username === '') {
                continue;
            }

            $realName = $this->getLdapAttributeValue($ldapUser, $dataSource['user_real_name_attribute']);
            $displayName = $this->getLdapAttributeValue($ldapUser, $dataSource['user_display_name_attribute']);
            $mail = $this->getLdapAttributeValue($ldapUser, $dataSource['user_mail_attribute']);
            $externalId = $this->getLdapAttributeValue($ldapUser, $dataSource['user_external_id_attribute']);
            $dn = $this->getLdapAttributeValue($ldapUser, $dataSource['user_dn_attribute'], $this->getLdapAttributeValue($ldapUser, 'dn'));

            $details[$username] = [
                'svn_user_name' => $username,
                'svn_user_real_name' => $realName,
                'svn_user_display_name' => $displayName !== '' ? $displayName : ($realName !== '' ? $realName : $username),
                'svn_user_mail' => $mail,
                'svn_user_external_id' => $externalId,
                'svn_user_dn' => $dn,
            ];
        }

        return $details;
    }

    private function buildLdapGroupDetails($ldapGroups, $dataSource, $groupNameAttribute)
    {
        $details = [];
        foreach ($ldapGroups as $ldapGroup) {
            $groupName = $this->getLdapAttributeValue($ldapGroup, $groupNameAttribute);
            if ($groupName === '') {
                continue;
            }

            $displayName = $this->getLdapAttributeValue($ldapGroup, $dataSource['group_display_name_attribute']);
            $externalId = $this->getLdapAttributeValue($ldapGroup, $dataSource['group_external_id_attribute']);
            $dn = $this->getLdapAttributeValue($ldapGroup, $dataSource['group_dn_attribute'], $this->getLdapAttributeValue($ldapGroup, 'dn'));

            $details[$groupName] = [
                'svn_group_name' => $groupName,
                'svn_group_display_name' => $displayName !== '' ? $displayName : $groupName,
                'svn_group_external_id' => $externalId,
                'svn_group_dn' => $dn,
            ];
        }

        return $details;
    }

    private function buildLdapMembershipIndex($users, $groups, $dataSource)
    {
        $userAttributes = $this->splitAttributes($dataSource['user_attributes']);
        $groupAttributes = $this->splitAttributes($dataSource['group_attributes']);
        $userNameAttribute = isset($userAttributes[0]) ? $userAttributes[0] : '';
        $groupNameAttribute = isset($groupAttributes[0]) ? $groupAttributes[0] : '';
        $userMemberAttribute = $dataSource['groups_to_user_attribute_value'];
        $groupMemberAttribute = !empty($dataSource['group_dn_attribute'])
            ? $dataSource['group_dn_attribute']
            : $dataSource['groups_to_user_attribute_value'];
        $groupMemberListAttribute = $dataSource['groups_to_user_attribute'];

        $userByMemberValue = [];
        foreach ($users as $user) {
            $userName = $this->getLdapAttributeValue($user, $userNameAttribute);
            if ($userName === '') {
                continue;
            }

            $candidateValues = array_merge(
                $this->getLdapAttributeValues($user, $userMemberAttribute),
                $this->getLdapAttributeValues($user, 'dn'),
                $this->getLdapAttributeValues($user, $userNameAttribute)
            );

            foreach ($candidateValues as $candidateValue) {
                $userByMemberValue[$this->normalizeLdapMemberValue($candidateValue)] = $userName;
            }
        }

        $groupByMemberValue = [];
        $groupMembers = [];
        $validGroupNames = [];
        foreach ($groups as $group) {
            $groupName = $this->getLdapAttributeValue($group, $groupNameAttribute);
            if ($groupName === '') {
                continue;
            }

            $checkResult = $this->checkService->CheckRepGroup($groupName);
            if ($checkResult['status'] != 1) {
                continue;
            }

            $validGroupNames[$groupName] = true;
            $groupMembers[$groupName] = $this->getLdapAttributeValues($group, $groupMemberListAttribute);

            $candidateValues = array_merge(
                $this->getLdapAttributeValues($group, $groupMemberAttribute),
                $this->getLdapAttributeValues($group, 'dn'),
                $this->getLdapAttributeValues($group, $groupNameAttribute),
                $this->getLdapAttributeValues($group, $dataSource['group_external_id_attribute'])
            );

            foreach ($candidateValues as $candidateValue) {
                $groupByMemberValue[$this->normalizeLdapMemberValue($candidateValue)] = $groupName;
            }
        }

        return [
            'userByMemberValue' => $userByMemberValue,
            'groupByMemberValue' => $groupByMemberValue,
            'groupMembers' => $groupMembers,
            'validGroupNames' => $validGroupNames,
        ];
    }

    private function resolveLdapGroupUsers($groupName, $membershipIndex, $nestedEnabled, $maxDepth, $depth = 0, $path = [])
    {
        if (!isset($membershipIndex['groupMembers'][$groupName])) {
            return [];
        }

        if (isset($path[$groupName]) || $depth > $maxDepth) {
            return [];
        }

        $path[$groupName] = true;
        $users = [];
        foreach ($membershipIndex['groupMembers'][$groupName] as $memberValue) {
            $memberKey = $this->normalizeLdapMemberValue($memberValue);

            if (isset($membershipIndex['userByMemberValue'][$memberKey])) {
                $users[] = $membershipIndex['userByMemberValue'][$memberKey];
                continue;
            }

            if (!$nestedEnabled || !isset($membershipIndex['groupByMemberValue'][$memberKey])) {
                continue;
            }

            $childGroupName = $membershipIndex['groupByMemberValue'][$memberKey];
            $users = array_merge(
                $users,
                $this->resolveLdapGroupUsers($childGroupName, $membershipIndex, $nestedEnabled, $maxDepth, $depth + 1, $path)
            );
        }

        return array_values(array_unique($users));
    }

    private function addAuthzGroupUser(&$authzContent, $groupName, $userName)
    {
        $checkResult = $this->checkService->CheckRepUser($userName);
        if ($checkResult['status'] != 1) {
            return message();
        }

        $result = $this->SVNAdmin->UpdGroupMember($authzContent, $groupName, $userName, 'user', 'add');
        if (is_numeric($result)) {
            if ($result == 612) {
                return message(200, 0, '文件格式错误(不存在[groups]标识)');
            } elseif ($result == 803) {
                return message();
            } else {
                return message(200, 0, "错误码$result");
            }
        }

        $authzContent = $result;
        return message();
    }

    /**
     * Searches for entries in the ldap.
     * 
     * Using PHP version < 5.4 will never return more than 1001 items.
     *
     * @param \LDAP\Connection $conn
     * @param string $protocolVersion
     * @param string $base_dn
     * @param string $search_filter
     * @param string $return_attributes
     * @param integer $pageSize
     * @return array of stdClass objects with property values defined by $return_attributes+"dn"
     */
    private function objectSearch($conn, $protocolVersion, $base_dn, $search_filter, $return_attributes, $pageSize = 100, $oid = '1.2.840.113556.1.4.319')
    {
        $current_version = PHP_VERSION;

        $range1 = '5.4.0';
        $range2 = '7.4.0';

        if (version_compare($current_version, $range1, '>=') && version_compare($current_version, $range2, '<')) {
            return $this->objectSearch_54_to_74($conn, $protocolVersion, $base_dn, $search_filter, $return_attributes, $pageSize = 100);
        } elseif (version_compare($current_version, $range2, '>=')) {
            return $this->objectSearch_74_to_80($conn, $protocolVersion, $base_dn, $search_filter, $return_attributes, $pageSize = 100, $oid = '1.2.840.113556.1.4.319');
        } else {
            return $this->objectSearch_74_to_80($conn, $protocolVersion, $base_dn, $search_filter, $return_attributes, $pageSize = 100, $oid = '1.2.840.113556.1.4.319');
        }
    }

    /**
     * [5.4   , 7.4.0)
     *
     * @param \LDAP\Connection $conn
     * @param string $protocolVersion
     * @param string $base_dn
     * @param string $search_filter
     * @param string $return_attributes
     * @param integer $pageSize
     * @return array of stdClass objects with property values defined by $return_attributes+"dn"
     */
    private function objectSearch_54_to_74($conn, $protocolVersion, $base_dn, $search_filter, $return_attributes, $pageSize = 100)
    {
        $base_dn = $this->prepareQueryString($base_dn, $protocolVersion);
        $search_filter = $this->prepareQueryString($search_filter, $protocolVersion);

        $ret = array();
        $pageCookie = "";
        do {
            ldap_control_paged_result($conn, $pageSize, true, $pageCookie);

            // Start search in LDAP directory.
            $sr = ldap_search($conn, $base_dn, $search_filter, $return_attributes, 0, 0, 0);
            if (!$sr) {
                break;
            }

            // Get the found entries as array.
            $entries = ldap_get_entries($conn, $sr);
            if (!$entries) {
                break;
            }

            $count = $entries["count"];
            for ($i = 0; $i < $count; ++$i) {
                // A $entry (array) contains all attributes of a single dataset from LDAP.
                $entry = $entries[$i];

                // Create a new object which will hold the attributes.
                // And add the default attribute "dn".
                $o = $this->createObjectFromEntry($entry, $protocolVersion);
                $ret[] = $o;
            }

            ldap_control_paged_result_response($conn, $sr, $pageCookie);
        } while ($pageCookie !== null && $pageCookie != "");
        return $ret;
    }

    /**
     * [7.4.0 , 8.0.0+]
     *
     * @param \LDAP\Connection $conn
     * @param string $protocolVersion
     * @param string $base_dn
     * @param string $search_filter
     * @param string $return_attributes
     * @param integer $pageSize
     * @param string $oid
     * @return array of stdClass objects with property values defined by $return_attributes+"dn"
     */
    private function objectSearch_74_to_80($conn, $protocolVersion, $base_dn, $search_filter, $return_attributes, $pageSize = 100, $oid = '1.2.840.113556.1.4.319')
    {
        $base_dn = $this->prepareQueryString($base_dn, $protocolVersion);
        $search_filter = $this->prepareQueryString($search_filter, $protocolVersion);

        $ret = [];

        $cookie = '';
        do {
            $controls = [
                [
                    'oid' => $oid,
                    // 'iscritical' => false,
                    'value' => ['size' => $pageSize, 'cookie' => $cookie]
                ]
            ];

            // Start search in LDAP directory.
            $sr = ldap_search($conn, $base_dn, $search_filter, $return_attributes, 0, 0, 0, 0, $controls);
            if (!$sr) {
                break;
            }

            // Get the found entries as array.
            $entries = ldap_get_entries($conn, $sr);
            if (!$entries) {
                break;
            }

            $count = $entries["count"];
            for ($i = 0; $i < $count; ++$i) {
                // A $entry (array) contains all attributes of a single dataset from LDAP.
                $entry = $entries[$i];

                // Create a new object which will hold the attributes.
                // And add the default attribute "dn".
                $o = $this->createObjectFromEntry($entry, $protocolVersion);
                $ret[] = $o;
            }

            ldap_parse_result($conn, $sr, $resultCode, $matchedDN, $errorMessage, $referrals, $serverControls);
            if (isset($serverControls[$oid]['value']['cookie'])) {
                // You need to pass the cookie from the last call to the next one
                $cookie = $serverControls[$oid]['value']['cookie'];
                // $pageSize = $count;
            } else {
                $cookie = '';
            }
        } while (!empty($cookie));

        return $ret;
    }

    /**
     * Creates a stdClass object with a property for each attribute.
     * For example:
     *   Entry ( "sn" => "Chuck Norris", "kick" => "Round house kick" )
     * Will return the stdClass object with following properties:
     *   stdClass->sn
     *   stdClass->kick
     *
     * @return stdClass
     */
    private function createObjectFromEntry(&$entry, $protocolVersion)
    {
        // Create a new user object which will hold the attributes.
        // And add the default attribute "dn".
        $u = new stdClass();
        $u->dn = $this->prepareResultString($entry["dn"], $protocolVersion);

        // The number of attributes inside the $entry array.
        $att_count = $entry["count"];

        for ($j = 0; $j < $att_count; $j++) {
            $attr_name = $entry[$j];
            $attr_value = $entry[$attr_name];
            $attr_value_count = $entry[$attr_name]["count"];

            // Use single scalar object for the attr value.
            if ($attr_value_count == 1) {
                $attr_single_value = $this->prepareResultString($attr_value[0], $protocolVersion);
                $u->$attr_name = $attr_single_value;
            } else {
                $attr_multi_value = array();
                for ($n = 0; $n < $attr_value_count; $n++) {
                    $attr_multi_value[] = $this->prepareResultString($attr_value[$n], $protocolVersion);
                }
                $u->$attr_name = $attr_multi_value;
            }
        }
        return $u;
    }

    /**
     * 测试连接ldap服务器
     *
     * @return void
     */
    public function LdapTest()
    {
        if (!function_exists('ldap_connect')) {
            return message(200, 0, '请先安装php的ldap依赖');
        }

        $checkResult = funCheckForm($this->payload, [
            'type' => ['type' => 'string', 'notNull' => true],
            'data_source' => ['type' => 'array', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $dataSource = $this->NormalizeDataSource([
            'ldap' => $this->payload['data_source']
        ], 'passwd')['ldap'];

        $type = $this->payload['type'];
        if (!in_array($type, ['connection', 'user', 'group'])) {
            return message(200, 0, '无效的验证类型');
        }

        $checkResult = funCheckForm($dataSource, [
            'ldap_host' => ['type' => 'string', 'notNull' => true],
            'ldap_port' => ['type' => 'integer'],
            'ldap_version' => ['type' => 'integer'],
            'ldap_bind_dn' => ['type' => 'string', 'notNull' => true],
            'ldap_bind_password' => ['type' => 'string', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        if (substr($dataSource['ldap_host'], 0, strlen('ldap://')) != 'ldap://' && substr($dataSource['ldap_host'], 0, strlen('ldaps://')) != 'ldaps://') {
            return message(200, 0, 'ldap主机名必须以 ldap:// 或者 ldaps:// 开始');
        }

        if (preg_match('/\:[0-9]+/', $dataSource['ldap_host'], $matches)) {
            return message(200, 0, 'ldap主机名不可携带端口');
        }

        $connection = ldap_connect(rtrim(trim($dataSource['ldap_host']), '/') . ':' . $dataSource['ldap_port'] . '/');
        if (!$connection) {
            return message(200, 0, '连接失败');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $dataSource['ldap_version']);

        // todo
        // ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 10);
        // ldap_set_option($connection, LDAP_OPT_REFERRALS, false);

        $result = @ldap_bind($connection, $dataSource['ldap_bind_dn'], $dataSource['ldap_bind_password']);
        if (!$result) {
            return message(200, 0, sprintf('连接失败: %s', ldap_error($connection)));
        }

        if ($type == 'connection') {
            return message();
        }

        if ($type == 'user') {
            $checkResult = funCheckForm($dataSource, [
                'ldap_host' => ['type' => 'string', 'notNull' => true],
                'ldap_port' => ['type' => 'integer'],
                'ldap_version' => ['type' => 'integer'],
                'ldap_bind_dn' => ['type' => 'string', 'notNull' => true],
                'ldap_bind_password' => ['type' => 'string', 'notNull' => true],

                'user_base_dn' => ['type' => 'string', 'notNull' => true],
                'user_search_filter' => ['type' => 'string', 'notNull' => true],
                'user_attributes' => ['type' => 'string', 'notNull' => true],
            ]);
            if ($checkResult['status'] == 0) {
                return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
            }

            // The standard attributes.
            $attributes = explode(',', $dataSource['user_attributes']);

            // Include the attribute which is used in the "member" attribute of a group-entry.
            if (isset($dataSource['groups_to_user_attribute_value'])) {
                $attributes[] = $dataSource['groups_to_user_attribute_value'];
            }

            $ldapUsers = $this->objectSearch($connection, $dataSource['ldap_version'], $dataSource['user_base_dn'], $dataSource['user_search_filter'], $attributes);

            $ldapUsersLen = count($ldapUsers);

            $up_name = $attributes[0];
            $users = [];
            for ($i = 0; $i < $ldapUsersLen; ++$i) {
                if (!property_exists($ldapUsers[$i], $up_name)) {
                    continue;
                }
                $users[] = $ldapUsers[$i]->$up_name;
            }

            return message(200, 1, '成功', [
                'count' => $ldapUsersLen,
                'users' => implode(',', $users),
                'success' => count($users),
                'fail' => $ldapUsersLen - count($users)
            ]);
        } elseif ($type == 'group') {
            $checkResult = funCheckForm($dataSource, [
                'ldap_host' => ['type' => 'string', 'notNull' => true],
                'ldap_port' => ['type' => 'integer'],
                'ldap_version' => ['type' => 'integer'],
                'ldap_bind_dn' => ['type' => 'string', 'notNull' => true],
                'ldap_bind_password' => ['type' => 'string', 'notNull' => true],

                'group_base_dn' => ['type' => 'string', 'notNull' => true],
                'group_search_filter' => ['type' => 'string', 'notNull' => true],
                'group_attributes' => ['type' => 'string', 'notNull' => true],
                'groups_to_user_attribute' => ['type' => 'string', 'notNull' => true],
                'groups_to_user_attribute_value' => ['type' => 'string', 'notNull' => true],
            ]);
            if ($checkResult['status'] == 0) {
                return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
            }

            $attributes = explode(',', $dataSource['group_attributes']);

            $includeMembers = false;
            if ($includeMembers) {
                $attributes[] = $dataSource['groups_to_user_attribute'];
            }

            $ldapGroups = $this->objectSearch($connection, $dataSource['ldap_version'], $dataSource['group_base_dn'], $dataSource['group_search_filter'], $attributes);

            $ldapGroupsLen = count($ldapGroups);

            $group_name_property = $attributes[0];
            $groups = [];
            for ($i = 0; $i < $ldapGroupsLen; ++$i) {
                if (!property_exists($ldapGroups[$i], $group_name_property)) {
                    continue;
                }
                $groups[] = $ldapGroups[$i]->$group_name_property;
            }

            return message(200, 1, '成功', [
                'count' => $ldapGroupsLen,
                'groups' => implode(',', $groups),
                'success' => count($groups),
                'fail' => $ldapGroupsLen - count($groups)
            ]);
        }
    }

    /**
     * ldap用户登录
     *
     * @return void
     */
    public function LdapUserLogin($username, $password)
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }
        $dataSource = $dataSource['ldap'];

        $connection = ldap_connect(rtrim(trim($dataSource['ldap_host']), '/') . ':' . $dataSource['ldap_port'] . '/');
        if (!$connection) {
            return false;
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $dataSource['ldap_version']);

        $result = @ldap_bind($connection, $dataSource['ldap_bind_dn'], $dataSource['ldap_bind_password']);
        if (!$result) {
            return false;
        }

        $attributes = $this->splitAttributes($dataSource['user_attributes']);

        $result = ldap_search($connection, $dataSource['user_base_dn'], sprintf('%s=%s', $attributes[0], $username));

        $entry = ldap_first_entry($connection, $result);

        $attrs = ldap_get_attributes($connection, $entry);

        $user_dn = ldap_get_dn($connection, $entry);

        $result = @ldap_bind($connection, $user_dn, $password);
        if (!$result) {
            return false;
        }

        return true;
    }

    /**
     * 获取ldap用户列表
     *
     * @return object
     */
    public function GetLdapUsers()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }
        $dataSource = $dataSource['ldap'];

        $connection = ldap_connect(rtrim(trim($dataSource['ldap_host']), '/') . ':' . $dataSource['ldap_port'] . '/');
        if (!$connection) {
            return message(200, 0, '连接失败');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $dataSource['ldap_version']);

        $result = @ldap_bind($connection, $dataSource['ldap_bind_dn'], $dataSource['ldap_bind_password']);
        if (!$result) {
            return message(200, 0, sprintf('连接失败: %s', ldap_error($connection)));
        }

        // The standard attributes.
        $attributes = $this->splitAttributes($dataSource['user_attributes']);

        // Include the attribute which is used in the "member" attribute of a group-entry.
        if (isset($dataSource['groups_to_user_attribute_value'])) {
            $this->appendAttribute($attributes, $dataSource['groups_to_user_attribute_value']);
        }
        $this->appendAttribute($attributes, $dataSource['user_real_name_attribute']);
        $this->appendAttribute($attributes, $dataSource['user_display_name_attribute']);
        $this->appendAttribute($attributes, $dataSource['user_mail_attribute']);
        $this->appendAttribute($attributes, $dataSource['user_external_id_attribute']);
        $this->appendAttribute($attributes, $dataSource['user_dn_attribute']);

        $ldapUsers = $this->objectSearch($connection, $dataSource['ldap_version'], $dataSource['user_base_dn'], $dataSource['user_search_filter'], $attributes);

        $ldapUsersLen = count($ldapUsers);

        $up_name = $attributes[0];
        $users = [];
        for ($i = 0; $i < $ldapUsersLen; ++$i) {
            $userName = $this->getLdapAttributeValue($ldapUsers[$i], $up_name);
            if ($userName === '') {
                continue;
            }
            $users[] = $userName;
        }

        return message(200, 1, '成功', [
            'object' => $ldapUsers,
            'users' => $users,
            'userDetails' => $this->buildLdapUserDetails($ldapUsers, $dataSource, $up_name)
        ]);
    }

    /**
     * 获取ldap分组列表
     *
     * @return array
     */
    public function GetLdapGroups($includeMembers = false)
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }
        $dataSource = $dataSource['ldap'];

        $connection = ldap_connect(rtrim(trim($dataSource['ldap_host']), '/') . ':' . $dataSource['ldap_port'] . '/');
        if (!$connection) {
            return message(200, 0, '连接失败');
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $dataSource['ldap_version']);

        $result = @ldap_bind($connection, $dataSource['ldap_bind_dn'], $dataSource['ldap_bind_password']);
        if (!$result) {
            return message(200, 0, sprintf('连接失败: %s', ldap_error($connection)));
        }

        $attributes = $this->splitAttributes($dataSource['group_attributes']);

        if ($includeMembers) {
            $this->appendAttribute($attributes, $dataSource['groups_to_user_attribute']);
            $this->appendAttribute($attributes, $dataSource['group_display_name_attribute']);
            $this->appendAttribute($attributes, $dataSource['group_external_id_attribute']);
            $this->appendAttribute($attributes, $dataSource['group_dn_attribute']);
        }

        $ldapGroups = $this->objectSearch($connection, $dataSource['ldap_version'], $dataSource['group_base_dn'], $dataSource['group_search_filter'], $attributes);

        $ldapGroupsLen = count($ldapGroups);

        $group_name_property = $attributes[0];
        $groups = [];
        for ($i = 0; $i < $ldapGroupsLen; ++$i) {
            $groupName = $this->getLdapAttributeValue($ldapGroups[$i], $group_name_property);
            if ($groupName === '') {
                continue;
            }
            $groups[] = $groupName;
        }

        return message(200, 1, '成功', [
            'object' => $ldapGroups,
            'groups' => $groups,
            'groupDetails' => $this->buildLdapGroupDetails($ldapGroups, $dataSource, $group_name_property)
        ]);
    }

    /**
     * 分组(ldap) => authz
     *
     * @return array
     */
    public function SyncLdapToAuthz()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }
        $dataSource = $dataSource['ldap'];

        $authzContent = $this->authzContent;
        $manualGroups = [];
        $dbManualGroups = $this->database->select('svn_groups', [
            'svn_group_name'
        ], [
            'svn_group_source' => 'manual'
        ]);
        $dbManualGroupNames = array_column($dbManualGroups, 'svn_group_name');
        if (!empty($dbManualGroupNames)) {
            $oldGroupInfo = $this->SVNAdmin->GetGroupInfo($authzContent);
            if (!is_numeric($oldGroupInfo)) {
                foreach ($oldGroupInfo as $groupInfo) {
                    if (in_array($groupInfo['groupName'], $dbManualGroupNames)) {
                        $manualGroups[$groupInfo['groupName']] = $groupInfo;
                    }
                }
            }
        }

        //清空原有分组
        $authzContent = $this->SVNAdmin->ClearGroupSection($authzContent);
        if (is_numeric($authzContent)) {
            if ($authzContent == 612) {
                return message(200, 0, '文件格式错误(不存在[groups]标识)');
            } else {
                return message(200, 0, "错误码$authzContent");
            }
        }

        //从ldap获取分组和用户
        $users = $this->GetLdapUsers();
        if ($users['status'] != 1) {
            return message($users['code'], $users['status'], $users['message'], $users['data']);
        }
        $users = $users['data']['object'];

        $groups = $this->GetLdapGroups(true);
        if ($groups['status'] != 1) {
            return message($groups['code'], $groups['status'], $groups['message'], $groups['data']);
        }
        $groups = $groups['data']['object'];

        $groupAttributes = $this->splitAttributes($dataSource['group_attributes']);
        $groupNameAttribute = isset($groupAttributes[0]) ? $groupAttributes[0] : '';
        $nestedEnabled = $this->configBool(isset($dataSource['group_nested_enabled']) ? $dataSource['group_nested_enabled'] : true, true);
        $maxDepth = isset($dataSource['group_nested_max_depth']) ? (int)$dataSource['group_nested_max_depth'] : 10;
        if ($maxDepth < 1) {
            $maxDepth = 1;
        } elseif ($maxDepth > 50) {
            $maxDepth = 50;
        }

        $membershipIndex = $this->buildLdapMembershipIndex($users, $groups, $dataSource);
        $groupDetails = $this->buildLdapGroupDetails($groups, $dataSource, $groupNameAttribute);
        foreach ($groups as $g) {
            $groupName = $this->getLdapAttributeValue($g, $groupNameAttribute);
            if ($groupName === '' || !isset($membershipIndex['validGroupNames'][$groupName])) {
                continue;
            }

            $result = $this->SVNAdmin->AddGroup($authzContent, $groupName);
            if (is_numeric($result)) {
                if ($result == 612) {
                    return message(200, 0, '文件格式错误(不存在[groups]标识)');
                } elseif ($result != 820) {
                    return message(200, 0, "错误码$result");
                }
            } else {
                $authzContent = $result;
            }

            $groupUsers = $this->resolveLdapGroupUsers($groupName, $membershipIndex, $nestedEnabled, $maxDepth);
            foreach ($groupUsers as $userName) {
                $result = $this->addAuthzGroupUser($authzContent, $groupName, $userName);
                if ($result['status'] != 1) {
                    return $result;
                }
            }
        }

        // Legacy direct-member block below is kept for compatibility but skipped after the indexed sync above.
        $groups = [];

        //一个分组条目中表示分组名的属性
        $groups_attributes = explode(',', $dataSource['group_attributes']);
        $gp_name = strtolower($groups_attributes[0]);

        //分组下包含多个对象 具备此属性的对象才可被识别为分组的成员 如 member
        $gp_member_id = strtolower($dataSource['groups_to_user_attribute']);

        //一个用户条目中表示用户名的属性
        $users_attributes = explode(',', $dataSource['user_attributes']);
        $up_name = strtolower($users_attributes[0]);

        //表示分组下的成员的唯一标识的属性 如 distinguishedName
        $up_id = strtolower($dataSource['groups_to_user_attribute_value']);

        foreach ($groups as $g) {
            if (!property_exists($g, $gp_name)) {
                //搜索的对象不存在 group-name
                continue;
            }

            //检查分组名是否合法
            $checkResult = $this->checkService->CheckRepGroup($g->$gp_name);
            if ($checkResult['status'] != 1) {
                continue;
            }

            //添加分组
            $result = $this->SVNAdmin->AddGroup($authzContent, $g->$gp_name);
            if (is_numeric($result)) {
                if ($result == 612) {
                    return message(200, 0, '文件格式错误(不存在[groups]标识)');
                } elseif ($result == 820) {
                    //分组已存在
                    continue;
                } else {
                    return message(200, 0, "错误码$result");
                }
            }
            $authzContent = $result;

            if (!property_exists($g, $gp_member_id)) {
                //分组下无成员
            } elseif (is_array($g->$gp_member_id)) {
                //分组下多个成员
                foreach ($g->$gp_member_id as $member_id) {
                    //获取成员用户名
                    foreach ($users as $u) {
                        if (!property_exists($u, $up_id)) {
                            continue;
                        }
                        if ($u->$up_id == $member_id) {
                            //为分组添加成员
                            $result = $this->SVNAdmin->UpdGroupMember($authzContent, $g->$gp_name, $u->$up_name, 'user', 'add');
                            if (is_numeric($result)) {
                                if ($result == 612) {
                                    return message(200, 0, '文件格式错误(不存在[groups]标识)');
                                } elseif ($result == 803) {
                                    $result = $authzContent;
                                } else {
                                    return message(200, 0, "错误码$result");
                                }
                            }
                            $authzContent = $result;
                            break;
                        }
                    }
                }
            } elseif (is_string($g->$gp_member_id)) {
                //分组下单个成员
                $member_id = $g->$gp_member_id;
                //获取成员用户名
                foreach ($users as $u) {
                    if ($u->$up_id == $member_id) {
                        //为分组添加成员
                        $result = $this->SVNAdmin->UpdGroupMember($authzContent, $g->$gp_name, $u->$up_name, 'user', 'add');
                        if (is_numeric($result)) {
                            if ($result == 612) {
                                return message(200, 0, '文件格式错误(不存在[groups]标识)');
                            } elseif ($result == 803) {
                                $result = $authzContent;
                            } else {
                                return message(200, 0, "错误码$result");
                            }
                        }
                        $authzContent = $result;
                        break;
                    }
                }
            }
        }

        foreach ($manualGroups as $manualGroupName => $manualGroup) {
            $result = $this->SVNAdmin->AddGroup($authzContent, $manualGroupName);
            if (!is_numeric($result)) {
                $authzContent = $result;
            } elseif ($result != 820) {
                return message(200, 0, "閿欒鐮?result");
            }

            foreach ($manualGroup['include']['users']['list'] as $member) {
                $result = $this->SVNAdmin->UpdGroupMember($authzContent, $manualGroupName, $member, 'user', 'add');
                if (!is_numeric($result)) {
                    $authzContent = $result;
                }
            }
            foreach ($manualGroup['include']['groups']['list'] as $member) {
                $result = $this->SVNAdmin->UpdGroupMember($authzContent, $manualGroupName, $member, 'group', 'add');
                if (!is_numeric($result)) {
                    $authzContent = $result;
                }
            }
            foreach ($manualGroup['include']['aliases']['list'] as $member) {
                $result = $this->SVNAdmin->UpdGroupMember($authzContent, $manualGroupName, $member, 'aliase', 'add');
                if (!is_numeric($result)) {
                    $authzContent = $result;
                }
            }
        }

        $writeResult = $this->WriteAuthzFile($authzContent);
        if ($writeResult['status'] != 1) {
            return $writeResult;
        }

        return message(200, 1, '成功', [
            'groupDetails' => $groupDetails,
        ]);
    }
}
