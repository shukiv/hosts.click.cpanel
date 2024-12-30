<?php
require '/usr/local/cpanel/php/cpanel.php';

$cpanel = new CPANEL();
echo $cpanel->header( 'Preview URL' );
$accountName = $cpanel->cpanelprint('$user');
$hostname = $cpanel->cpanelprint('$hostname');
$ip = $cpanel->cpanelprint('$ip');


$domainData = [];
// Call the API
$response = $cpanel->uapi(
    'DomainInfo',
    'domains_data'
);

// Handle the response
if ($response['cpanelresult']['result']['status']) {
    $data = $response['cpanelresult']['result']['data'];
    // Do something with the $data
    // So you can see the data shape we print it here.
    //var_dump($data);
    $domainData = $data['addon_domains'] ?? [];

    if (isset($data['main_domain'])) {
		$domainData['main'] = $data['main_domain'];
		if (isset($data['sub_domains'])) {
			$domainData['main']['sub_domains'] = $data['sub_domains'];
		}
	} else {
		if (isset($data['sub_domains'])) {
			$domainData = $data['sub_domains'];
		}
	}
	
}
else {
    // Report errors:
    echo '<pre>';
    var_dump($response['cpanelresult']['result']['errors']);
    echo '</pre>';
}
?>
<iframe id="myiframe" frameBorder="0" src="https://hosts.click/cpanel.html?ip=<?php echo $ip; ?>&domains=<?php foreach ($domainData as $domain) {echo $domain['domain'].','; } ?>" style="width: 100%; height: 1200px;"></iframe>
<?php
echo $cpanel->footer();
$cpanel->end();
?>
