<?php
// Campaign hadiah gratis (admin/campaigns.php): tabel campaigns + campaign_claims.
require_once __DIR__ . '/../includes/campaign.php';

return static function (PDO $pdo): void {
    campaignCreateTables($pdo);
};
