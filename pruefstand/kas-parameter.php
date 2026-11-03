<?php

declare(strict_types=1);

/*
 * Erlaubte Parameter je KAS-Funktion für die KAS-Attrappe.
 *
 * Erzeugt aus der offiziellen Dokumentation (kasapi.kasserver.com/dokumentation/phpdoc,
 * Stand 2026-11-03); dazu die wenigen Namen, die nur in den Beispielen der
 * Dokumentation stehen (inst_fpse bei add_account, samba_password,
 * mail_adress). „target_N“ steht für target_0, target_1 …
 */

return array (
  'add_account' => 
  array (
    0 => 'account_kas_password',
    1 => 'account_ftp_password',
    2 => 'account_comment',
    3 => 'account_contact_mail',
    4 => 'hostname_art',
    5 => 'hostname_part1',
    6 => 'hostname_part2',
    7 => 'hostname_path',
    8 => 'max_account',
    9 => 'max_domain',
    10 => 'max_subdomain',
    11 => 'max_webspace',
    12 => 'max_mail_account',
    13 => 'max_mail_forward',
    14 => 'max_mailinglist',
    15 => 'max_database',
    16 => 'max_ftpuser',
    17 => 'max_sambauser',
    18 => 'max_cronjobs',
    19 => 'max_wbk',
    20 => 'inst_htaccess',
    21 => 'kas_access_forbidden',
    22 => 'inst_software',
    23 => 'logging',
    24 => 'logage',
    25 => 'statistic',
    26 => 'dns_settings',
    27 => 'show_password',
    28 => 'inst_fpse',
  ),
  'add_cronjob' => 
  array (
    0 => 'protocol',
    1 => 'http_url',
    2 => 'cronjob_comment',
    3 => 'minute',
    4 => 'hour',
    5 => 'day_of_month',
    6 => 'month',
    7 => 'day_of_week',
    8 => 'http_user',
    9 => 'http_password',
    10 => 'mail_address',
    11 => 'mail_condition',
    12 => 'mail_subject',
    13 => 'is_active',
    14 => 'mail_adress',
  ),
  'add_database' => 
  array (
    0 => 'database_password',
    1 => 'database_comment',
    2 => 'database_allowed_hosts',
  ),
  'add_ddnsuser' => 
  array (
    0 => 'dyndns_comment',
    1 => 'dyndns_password',
    2 => 'dyndns_zone',
    3 => 'dyndns_label',
    4 => 'dyndns_target_ip',
    5 => 'dyndns_dual_stack',
  ),
  'add_directoryprotection' => 
  array (
    0 => 'directory_user',
    1 => 'directory_path',
    2 => 'directory_password',
    3 => 'directory_authname',
  ),
  'add_dns_settings' => 
  array (
    0 => 'zone_host',
    1 => 'record_type',
    2 => 'record_name',
    3 => 'record_data',
    4 => 'record_aux',
  ),
  'add_domain' => 
  array (
    0 => 'domain_name',
    1 => 'domain_tld',
    2 => 'domain_path',
    3 => 'redirect_status',
    4 => 'statistic_version',
    5 => 'statistic_language',
    6 => 'php_version',
  ),
  'add_ftpusers' => 
  array (
    0 => 'ftp_password',
    1 => 'ftp_comment',
    2 => 'ftp_path',
    3 => 'ftp_permission_read',
    4 => 'ftp_permission_write',
    5 => 'ftp_permission_list',
    6 => 'ftp_virus_clamav',
  ),
  'add_mailaccount' => 
  array (
    0 => 'mail_password',
    1 => 'webmail_autologin',
    2 => 'local_part',
    3 => 'domain_part',
    4 => 'responder',
    5 => 'mail_responder_content_type',
    6 => 'mail_responder_displayname',
    7 => 'responder_text',
    8 => 'copy_adress',
    9 => 'mail_sender_alias',
    10 => 'mail_xlist_enabled',
    11 => 'mail_xlist_sent',
    12 => 'mail_xlist_drafts',
    13 => 'mail_xlist_trash',
    14 => 'mail_xlist_spam',
    15 => 'mail_xlist_archiv',
    16 => 'mail_allow_nets',
  ),
  'add_mailforward' => 
  array (
    0 => 'local_part',
    1 => 'domain_part',
    2 => 'target_N',
  ),
  'add_mailinglist' => 
  array (
    0 => 'mailinglist_name',
    1 => 'mailinglist_domain',
    2 => 'mailinglist_password',
  ),
  'add_mailstandardfilter' => 
  array (
    0 => 'mail_login',
    1 => 'filter',
  ),
  'add_sambauser' => 
  array (
    0 => 'samba_path',
    1 => 'samba_new_password',
    2 => 'samba_comment',
    3 => 'samba_password',
  ),
  'add_session' => 
  array (
    0 => 'session_lifetime',
    1 => 'session_update_lifetime',
    2 => 'session_2fa',
  ),
  'add_softwareinstall' => 
  array (
    0 => 'software_id',
    1 => 'software_database',
    2 => 'software_database_password',
    3 => 'software_hostname',
    4 => 'software_install_example_data',
    5 => 'software_path',
    6 => 'software_admin_mail',
    7 => 'software_admin_user',
    8 => 'software_admin_pass',
    9 => 'language',
  ),
  'add_subdomain' => 
  array (
    0 => 'subdomain_name',
    1 => 'domain_name',
    2 => 'subdomain_path',
    3 => 'redirect_status',
    4 => 'statistic_version',
    5 => 'statistic_language',
    6 => 'php_version',
  ),
  'add_symlink' => 
  array (
    0 => 'symlink_target',
    1 => 'symlink_name',
  ),
  'delete_account' => 
  array (
    0 => 'account_login',
  ),
  'delete_cronjob' => 
  array (
    0 => 'cronjob_id',
  ),
  'delete_database' => 
  array (
    0 => 'database_login',
  ),
  'delete_ddnsuser' => 
  array (
    0 => 'dyndns_login',
  ),
  'delete_directoryprotection' => 
  array (
    0 => 'directory_user',
    1 => 'directory_path',
  ),
  'delete_dns_settings' => 
  array (
    0 => 'record_id',
  ),
  'delete_domain' => 
  array (
    0 => 'domain_name',
  ),
  'delete_ftpuser' => 
  array (
    0 => 'ftp_login',
  ),
  'delete_mailaccount' => 
  array (
    0 => 'mail_login',
  ),
  'delete_mailforward' => 
  array (
    0 => 'mail_forward',
  ),
  'delete_mailinglist' => 
  array (
    0 => 'mailinglist_name',
  ),
  'delete_mailstandardfilter' => 
  array (
    0 => 'mail_login',
  ),
  'delete_sambauser' => 
  array (
    0 => 'samba_login',
  ),
  'delete_subdomain' => 
  array (
    0 => 'subdomain_name',
  ),
  'get_accountresources' => 
  array (
  ),
  'get_accounts' => 
  array (
    0 => 'account_login',
  ),
  'get_accountsettings' => 
  array (
  ),
  'get_cronjobs' => 
  array (
    0 => 'cronjob_id',
  ),
  'get_databases' => 
  array (
    0 => 'database_login',
  ),
  'get_ddnsusers' => 
  array (
    0 => 'ddns_login',
  ),
  'get_directoryprotection' => 
  array (
    0 => 'directory_path',
  ),
  'get_dns_settings' => 
  array (
    0 => 'zone_host',
    1 => 'record_id',
  ),
  'get_domains' => 
  array (
    0 => 'domain_name',
  ),
  'get_ftpusers' => 
  array (
    0 => 'ftp_login',
  ),
  'get_mailaccounts' => 
  array (
    0 => 'mail_login',
  ),
  'get_mailforwards' => 
  array (
    0 => 'mail_forward',
  ),
  'get_mailinglists' => 
  array (
    0 => 'mailinglist_name',
  ),
  'get_mailstandardfilter' => 
  array (
  ),
  'get_sambausers' => 
  array (
    0 => 'samba_login',
  ),
  'get_server_information' => 
  array (
  ),
  'get_softwareinstall' => 
  array (
    0 => 'software_id',
  ),
  'get_space' => 
  array (
    0 => 'show_subaccounts',
    1 => 'show_details',
  ),
  'get_space_usage' => 
  array (
    0 => 'directory',
  ),
  'get_subdomains' => 
  array (
    0 => 'subdomain_name',
  ),
  'get_topleveldomains' => 
  array (
  ),
  'get_traffic' => 
  array (
    0 => 'year',
    1 => 'month',
  ),
  'move_domain' => 
  array (
    0 => 'domain_name',
    1 => 'source_account',
    2 => 'target_account',
  ),
  'move_subdomain' => 
  array (
    0 => 'subdomain_name',
    1 => 'source_account',
    2 => 'target_account',
  ),
  'reset_dns_settings' => 
  array (
    0 => 'zone_host',
    1 => 'nameserver',
  ),
  'update_account' => 
  array (
    0 => 'account_login',
    1 => 'account_kas_password',
    2 => 'max_account',
    3 => 'max_domain',
    4 => 'max_subdomain',
    5 => 'max_webspace',
    6 => 'max_mail_account',
    7 => 'max_mail_forward',
    8 => 'max_mailinglist',
    9 => 'max_database',
    10 => 'max_ftpuser',
    11 => 'max_sambauser',
    12 => 'max_cronjobs',
    13 => 'max_wbk',
    14 => 'inst_htaccess',
    15 => 'inst_fpse',
    16 => 'kas_access_forbidden',
    17 => 'show_password',
    18 => 'inst_software',
    19 => 'logging',
    20 => 'logage',
    21 => 'statistic',
    22 => 'dns_settings',
    23 => 'account_comment',
    24 => 'account_contact_mail',
  ),
  'update_accountsettings' => 
  array (
    0 => 'account_password',
    1 => 'show_password',
    2 => 'logging',
    3 => 'logage',
    4 => 'statistic',
    5 => 'account_comment',
    6 => 'account_contact_mail',
  ),
  'update_chown' => 
  array (
    0 => 'chown_path',
    1 => 'chown_user',
    2 => 'recursive',
  ),
  'update_cronjob' => 
  array (
    0 => 'cronjob_id',
    1 => 'protocol',
    2 => 'http_url',
    3 => 'cronjob_comment',
    4 => 'minute',
    5 => 'hour',
    6 => 'day_of_month',
    7 => 'month',
    8 => 'day_of_week',
    9 => 'http_user',
    10 => 'http_password',
    11 => 'mail_address',
    12 => 'mail_condition',
    13 => 'mail_subject',
    14 => 'is_active',
    15 => 'mail_adress',
  ),
  'update_database' => 
  array (
    0 => 'database_login',
    1 => 'database_new_password',
    2 => 'database_comment',
    3 => 'database_allowed_hosts',
  ),
  'update_ddnsuser' => 
  array (
    0 => 'dyndns_login',
    1 => 'dyndns_password',
    2 => 'dyndns_comment',
    3 => 'dyndns_dual_stack',
  ),
  'update_directoryprotection' => 
  array (
    0 => 'directory_user',
    1 => 'directory_path',
    2 => 'directory_password',
    3 => 'directory_authname',
  ),
  'update_dns_settings' => 
  array (
    0 => 'record_id',
    1 => 'record_name',
    2 => 'record_data',
    3 => 'record_aux',
  ),
  'update_domain' => 
  array (
    0 => 'domain_name',
    1 => 'domain_path',
    2 => 'redirect_status',
    3 => 'php_version',
    4 => 'is_active',
  ),
  'update_ftpuser' => 
  array (
    0 => 'ftp_login',
    1 => 'ftp_path',
    2 => 'ftp_new_password',
    3 => 'ftp_comment',
    4 => 'ftp_permission_read',
    5 => 'ftp_permission_write',
    6 => 'ftp_permission_list',
    7 => 'ftp_virus_clamav',
  ),
  'update_mailaccount' => 
  array (
    0 => 'mail_login',
    1 => 'mail_new_password',
    2 => 'webmail_autologin',
    3 => 'responder',
    4 => 'mail_responder_content_type',
    5 => 'mail_responder_displayname',
    6 => 'responder_text',
    7 => 'copy_adress',
    8 => 'is_active',
    9 => 'mail_sender_alias',
    10 => 'mail_xlist_enabled',
    11 => 'mail_xlist_sent',
    12 => 'mail_xlist_drafts',
    13 => 'mail_xlist_trash',
    14 => 'mail_xlist_spam',
    15 => 'mail_xlist_archiv',
    16 => 'mail_allow_nets',
  ),
  'update_mailforward' => 
  array (
    0 => 'mail_forward',
    1 => 'target_N',
  ),
  'update_mailinglist' => 
  array (
    0 => 'mailinglist_name',
    1 => 'subscriber',
    2 => 'restrict_post',
    3 => 'config',
    4 => 'is_active',
  ),
  'update_sambauser' => 
  array (
    0 => 'samba_login',
    1 => 'samba_path',
    2 => 'samba_new_password',
    3 => 'samba_comment',
  ),
  'update_ssl' => 
  array (
    0 => 'hostname',
    1 => 'ssl_certificate_is_active',
    2 => 'ssl_certificate_sni_csr',
    3 => 'ssl_certificate_sni_key',
    4 => 'ssl_certificate_sni_crt',
    5 => 'ssl_certificate_sni_bundle',
    6 => 'ssl_certificate_force_https',
    7 => 'ssl_certificate_hsts_max_age',
  ),
  'update_subdomain' => 
  array (
    0 => 'subdomain_name',
    1 => 'subdomain_path',
    2 => 'redirect_status',
    3 => 'php_version',
    4 => 'is_active',
  ),
  'update_superusersettings' => 
  array (
    0 => 'account_login',
    1 => 'ssh_access',
    2 => 'ssh_keys',
  ),
);
