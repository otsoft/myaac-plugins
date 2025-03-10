<?php
/**
 * Powerful guilds for MyAAC
 *
 * @name      powerful-guilds
 * @author    Slawkens <slawkens@gmail.com>
 */

if(!isset($config['powerful_guilds']))
{
	$config['powerful_guilds'] = [
		'refresh_interval' => 10 * 60, // cache query for 10 minutes (in seconds)
		'amount' => 5, // how many powerful guilds to show
		'page' => 'news' // on what pages most powerful guilds box should appear, for example 'news', or 'guilds' (blank makes it visible on every page)
	];
}

function mostPowerfulGuildsDatabase()
{
	global $db, $config;

	$ret = array();
	if(tableExist('killers')) { // TFS 0.3 + 0.4
		foreach ($db->query('SELECT `g`.`id` AS `id`, `g`.`name` AS `name`,
		`g`.`logo_name` AS `logo_name`, COUNT(`g`.`name`) as `frags`
		FROM `killers` k
			LEFT JOIN `player_killers` pk ON `k`.`id` = `pk`.`kill_id`
			LEFT JOIN `players` p ON `pk`.`player_id` = `p`.`id`
			LEFT JOIN `guild_ranks` gr ON `p`.`rank_id` = `gr`.`id`
			LEFT JOIN `guilds` g ON `gr`.`guild_id` = `g`.`id`
		WHERE `k`.`unjustified` = 1 AND `k`.`final_hit` = 1
			GROUP BY `name`
			ORDER BY `frags` DESC, `name` ASC
			LIMIT 0, ' . $config['powerful_guilds']['amount'] . ';') as $guild)
				$ret[] = array('id' => $guild['id'], 'name' => $guild['name'], 'logo' => $guild['logo'], 'frags' => $guild['frags']);
	}
	else { // TFS 1.0+
		foreach($db->query('SELECT `g`.`id` AS `id`, `g`.`name` AS `name`,
		`g`.`logo_name` AS `logo_name`, COUNT(`g`.`name`) as `frags`
		FROM `players` p
			LEFT JOIN `player_deaths` pd ON `pd`.`killed_by` = `p`.`name`
			LEFT JOIN `guild_membership` gm ON `p`.`id` = `gm`.`player_id`
			LEFT JOIN `guilds` g ON `gm`.`guild_id` = `g`.`id`
		WHERE `pd`.`unjustified` = 1 AND g.name is NOT NULL
			GROUP BY `name`
			ORDER BY `frags` DESC, `name` ASC
			LIMIT 0, ' . $config['powerful_guilds']['amount'] . ';') as $guild) {
				$ret[] = array('id' => $guild['id'], 'name' => $guild['name'], 'logo' => $guild['logo'], 'frags' => $guild['frags']);
		}
	}

	return $ret;
}

function mostPowerfulGuildsList()
{
	global $cache, $config;

	if(!$cache->enabled())
		return mostPowerfulGuildsDatabase();

	$ret = array();
	$tmp = '';
	if($cache->fetch('powerful_guilds', $tmp))
		$ret = unserialize($tmp);

	if(!isset($ret[0]) || $ret['updated'] + $config['powerful_guilds']['refresh_interval'] < time())
	{
		$ret = mostPowerfulGuildsDatabase();
		$ret['updated'] = time();
		$cache->set('powerful_guilds', serialize($ret));
	}

	unset($ret['updated']);
	return $ret;
}

global $twig_loader;
$twig->addGlobal('config', $config);
$twig_loader->prependPath(__DIR__);

$_page = $config['powerful_guilds']['page'];
if(!isset($_page[0]) || $_page == PAGE)
{
	$guilds = mostPowerfulGuildsList();
	// just for testing purposes if you don't have any kills on server
	//$guilds = $db->query('SELECT * FROM guilds LIMIT ' . $config['powerful_guilds']['amount'])->fetchAll();
	if(count($guilds) > 0) {
		foreach($guilds as &$guild) {
			$guild['link'] = getGuildLink($guild['name'], false);
			$guild['logo'] = ((!empty($guild['logo_name']) && file_exists(BASE . 'images/guilds/' . $guild['logo_name'])) ?
				$guild['logo_name'] : 'default.gif');
			//$guild['frags'] = rand(1, 100);
		}
	}

	$twig->display('powerful-guilds.html.twig', [
		'guilds' => $guilds
	]);
	return true;
}
