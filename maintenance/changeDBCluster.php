<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\MediaWikiServices;

class ChangeDBCluster extends Maintenance {
	private $dbw = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'db-cluster', 'Sets the wikis requested to a different db cluster.', true, true );
		$this->addOption( 'file', 'Path to file where the wikinames are store. Must be one wikidb name per line. (Optional, fallsback to current dbname)', false, true );
	}

	public function execute() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'createwiki' );
		$this->dbw = wfGetDB( DB_MASTER, [], $config->get( 'CreateWikiDatabase' ) );

		if ( (bool)$this->getOption( 'file' ) ) {
			$file = fopen( $this->getOption( 'file' ), 'r' );
			if ( !$file ) {
				$this->fatalError( "Unable to read file, exiting" );
			}
		} else {
			$this->updateDbCluster( $config->get( 'DBname' ) );

			$this->recacheDBListJson( $config->get( 'DBname' ) );
			$this->recacheWikiJson( $config->get( 'DBname' ) );
			return;
		}

		for ( $linenum = 1; !feof( $file ); $linenum++ ) {
			$line = trim( fgets( $file ) );
			if ( $line == '' ) {
				continue;
			}

			$this->updateDbCluster( $line );
			$this->recacheWikiJson( $line );
		}

		$this->recacheDBListJson( $config->get( 'DBname' ) );
	}

	private function updateDbCluster( string $wiki ) {
		$this->dbw->update(
			'cw_wikis',
			[
				'wiki_dbcluster' => (string)$this->getOption( 'db-cluster' ),
			],
			[
				'wiki_dbname' => $wiki,
			],
			__METHOD__
		);
	}

	private function recacheWikiJson( string $wiki ) {
		$cWJ = new CreateWikiJson( $wiki );
		$cWJ->resetWiki();
		$cWJ->update();
	}

	private function recacheDBListJson( string $wiki ) {
		$cWJ = new CreateWikiJson( $wiki );
		$cWJ->resetDatabaseList();
		$cWJ->update();
	}
}

$maintClass = 'ChangeDBCluster';
require_once RUN_MAINTENANCE_IF_MAIN;
