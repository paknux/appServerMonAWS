<?php
// aws_meta.php
// Ambil informasi instance EC2 via Instance Metadata Service v2 (IMDSv2).
// Tidak butuh AWS SDK/Composer — cukup ekstensi php-curl (apt install php-curl).

define('IMDS_BASE', 'http://169.254.169.254/latest');
define('IMDS_TIMEOUT', 2); // detik, biar dashboard tidak nge-hang kalau bukan di EC2

function imds_get_token() {
    $ch = curl_init(IMDS_BASE . '/api/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => ['X-aws-ec2-metadata-token-ttl-seconds: 21600'],
        CURLOPT_TIMEOUT => IMDS_TIMEOUT,
    ]);
    $token = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    return $ok ? $token : null;
}

function imds_fetch($path, $token) {
    $ch = curl_init(IMDS_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $token ? ["X-aws-ec2-metadata-token: $token"] : [],
        CURLOPT_TIMEOUT => IMDS_TIMEOUT,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? trim($res) : null;
}

function get_aws_info() {
    $token = imds_get_token();
    if (!$token) {
        return ['available' => false, 'reason' => 'IMDS tidak terjangkau (bukan EC2, atau IMDSv2 diblok)'];
    }

    $get = fn($p) => imds_fetch($p, $token);

    // Dynamic data untuk info akun & region lengkap
    $identityJson = $get('/dynamic/instance-identity/document');
    $identity = $identityJson ? json_decode($identityJson, true) : [];

    // IAM role yang attached ke instance (kalau ada)
    $iamRole = $get('/meta-data/iam/security-credentials/');

    // ARN identitas IAM (instance profile) — ini "akun AWS" yang dipakai instance
    $iamInfoJson = $get('/meta-data/iam/info');
    $iamInfo = $iamInfoJson ? json_decode($iamInfoJson, true) : null;

    // Detail kredensial sementara (expiration, type) — TANPA expose secret key
    $credInfo = null;
    if ($iamRole) {
        $credJson = $get('/meta-data/iam/security-credentials/' . $iamRole);
        $cred = $credJson ? json_decode($credJson, true) : null;
        if ($cred) {
            $credInfo = [
                'type' => $cred['Type'] ?? null,
                'access_key_id' => $cred['AccessKeyId'] ?? null, // ini public identifier, aman ditampilkan
                'expiration' => $cred['Expiration'] ?? null,
                'last_updated' => $cred['LastUpdated'] ?? null,
            ];
        }
    }

    return [
        'available' => true,
        'instance_id' => $get('/meta-data/instance-id'),
        'instance_type' => $get('/meta-data/instance-type'),
        'ami_id' => $get('/meta-data/ami-id'),
        'hostname' => $get('/meta-data/hostname'),
        'local_hostname' => $get('/meta-data/local-hostname'),
        'public_ipv4' => $get('/meta-data/public-ipv4'),
        'local_ipv4' => $get('/meta-data/local-ipv4'),
        'availability_zone' => $get('/meta-data/placement/availability-zone'),
        'region' => $identity['region'] ?? null,
        'account_id' => $identity['accountId'] ?? null,
        'iam_role' => $iamRole ?: 'Tidak ada IAM role terpasang',
        'iam_arn' => $iamInfo['InstanceProfileArn'] ?? null,
        'iam_profile_id' => $iamInfo['InstanceProfileId'] ?? null,
        'iam_cred_type' => $credInfo['type'] ?? null,
        'iam_access_key_id' => $credInfo['access_key_id'] ?? null,
        'iam_cred_expiration' => $credInfo['expiration'] ?? null,
        'security_groups' => $get('/meta-data/security-groups'),
        'mac' => $get('/meta-data/mac'),
    ];
}
