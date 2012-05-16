<?php

/**
 * File defining the settings for the Wikibase extension.
 * More info can be found at https://www.mediawiki.org/wiki/Extension:Wikibase#Settings
 *
 * NOTICE:
 * Changing one of these settings can be done by assigning to $egWBSettings,
 * AFTER the inclusion of the extension itself.
 *
 * @since 0.1
 *
 * @file Wikibase.settings.php
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class WBSettings {

	/**
	 * Build version of the settings.
	 * @since 0.1
	 * @var boolean
	 */
	protected $settings = false;

	/**
	 * Returns an array with all settings after making sure they are
	 * initialized (ie set settings have been merged with the defaults).
	 * setting name (string) => setting value (mixed)
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public function getSettings() {
		$this->buildSettings();
		return $this->settings;
	}

	/**
	 * Returns an instance of the settings class.
	 *
	 * @since 0.1
	 *
	 * @return WBCSettings
	 */
	public static function singleton() {
		static $instance = false;

		if ( $instance === false ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Does a lazy rebuild of the settings.
	 *
	 * @since 0.1
	 */
	public function rebuildSettings() {
		$this->settings = false;
	}

	/**
	 * Builds the settings if needed.
	 * This includes merging the set settings over the default ones.
	 *
	 * @since 0.1
	 */
	protected function buildSettings() {
		if ( $this->settings === false ) {
			$this->settings = array_merge(
				self::getDefaultSettings(),
				$GLOBALS['egWBSettings']
			);
		}
	}

	/**
	 * Gets the value of the specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $settingName
	 *
	 * @throws MWException
	 * @return mixed
	 */
	public function getSetting( $settingName, $default=null ) {
		$this->buildSettings();

		if ( !array_key_exists( $settingName, $this->settings ) ) {
			if ($default === null) {
				throw new MWException( 'Attempt to get non-existing setting "' . $settingName . '"' );
			}
			return $default;
		}

		return $this->settings[$settingName];
	}

	/**
	 * Gets the value of the specified setting.
	 * Shortcut to calling getSetting on the singleton instance of the settings class.
	 *
	 * @since 0.1
	 *
	 * @param string $settingName
	 *
	 * @return mixed
	 */
	public static function get( $settingName, $default=null ) {
		return self::singleton()->getSetting( $settingName, $default );
	}

	/**
	 * Returns the default values for the settings.
	 * setting name (string) => setting value (mixed)
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	protected static function getDefaultSettings() {
		return array(
			// alternative: application/vnd.php.serialized
			'serializationFormat' => CONTENT_FORMAT_JSON,

			// Disables token and post requirements in the API to
			// facilitate testing, do not turn on in production!
			'apiInDebug' => false,

			'apiDebugWithTokens' => false,

			// The site link sites we can link to (not necessarily clients!)
			// They are grouped, each group has a 'sites' element which is an array holding the identifiers.
			// It also can hold defaultSiteUrlPath and defaultSiteFilePath overriding the global default.
			// Each element in the 'sites' array contains the identifier for the site (which should be unique!)
			// pointing to the url of the site, or an array with the url (element: site) and optionally
			// the filepath and urlpath, using these words as keys.
			// TODO: add stuff to hold message keys for short and long names
			'siteIdentifiers' => array(
				'wikipedia' => array(
					'sites' => array(
						'ab' => 'http://ab.wikipedia.org',
						'af' => 'http://af.wikipedia.org',
						'ak' => 'http://ak.wikipedia.org',
						'als' => 'http://als.wikipedia.org',
						'am' => 'http://am.wikipedia.org',
						'an' => 'http://an.wikipedia.org',
						'ang' => 'http://ang.wikipedia.org',
						'ar' => 'http://ar.wikipedia.org',
						'arc' => 'http://arc.wikipedia.org',
						'arz' => 'http://arz.wikipedia.org',
						'as' => 'http://as.wikipedia.org',
						'ast' => 'http://ast.wikipedia.org',
						'av' => 'http://av.wikipedia.org',
						'ay' => 'http://ay.wikipedia.org',
						'az' => 'http://az.wikipedia.org',
						'ba' => 'http://ba.wikipedia.org',
						'bar' => 'http://bar.wikipedia.org',
						'bat-smg' => 'http://bat-smg.wikipedia.org',
						'bcl' => 'http://bcl.wikipedia.org',
						'be' => 'http://be.wikipedia.org',
						'be-x-old' => 'http://be-x-old.wikipedia.org',
						'bg' => 'http://bg.wikipedia.org',
						'bh' => 'http://bh.wikipedia.org',
						'bi' => 'http://bi.wikipedia.org',
						'bm' => 'http://bm.wikipedia.org',
						'bn' => 'http://bn.wikipedia.org',
						'bo' => 'http://bo.wikipedia.org',
						'bpy' => 'http://bpy.wikipedia.org',
						'br' => 'http://br.wikipedia.org',
						'bs' => 'http://bs.wikipedia.org',
						'bug' => 'http://bug.wikipedia.org',
						'bxr' => 'http://bxr.wikipedia.org',
						'ca' => 'http://ca.wikipedia.org',
						'cbk-zam' => 'http://cbk-zam.wikipedia.org',
						'cdo' => 'http://cdo.wikipedia.org',
						'ce' => 'http://ce.wikipedia.org',
						'ceb' => 'http://ceb.wikipedia.org',
						'ch' => 'http://ch.wikipedia.org',
						'cho' => 'http://cho.wikipedia.org',
						'chr' => 'http://chr.wikipedia.org',
						'chy' => 'http://chy.wikipedia.org',
						'co' => 'http://co.wikipedia.org',
						'cr' => 'http://cr.wikipedia.org',
						'crh' => 'http://crh.wikipedia.org',
						'cs' => 'http://cs.wikipedia.org',
						'csb' => 'http://csb.wikipedia.org',
						'cu' => 'http://cu.wikipedia.org',
						'cv' => 'http://cv.wikipedia.org',
						'cy' => 'http://cy.wikipedia.org',
						'da' => 'http://da.wikipedia.org',
						'de' => 'http://de.wikipedia.org',
						'diq' => 'http://diq.wikipedia.org',
						'dsb' => 'http://dsb.wikipedia.org',
						'dv' => 'http://dv.wikipedia.org',
						'dz' => 'http://dz.wikipedia.org',
						'ee' => 'http://ee.wikipedia.org',
						'el' => 'http://el.wikipedia.org',
						'eml' => 'http://eml.wikipedia.org',
						'en' => 'http://en.wikipedia.org',
						'eo' => 'http://eo.wikipedia.org',
						'es' => 'http://es.wikipedia.org',
						'et' => 'http://et.wikipedia.org',
						'eu' => 'http://eu.wikipedia.org',
						'fa' => 'http://fa.wikipedia.org',
						'ff' => 'http://ff.wikipedia.org',
						'fi' => 'http://fi.wikipedia.org',
						'fiu-vro' => 'http://fiu-vro.wikipedia.org',
						'fj' => 'http://fj.wikipedia.org',
						'fo' => 'http://fo.wikipedia.org',
						'fr' => 'http://fr.wikipedia.org',
						'frp' => 'http://frp.wikipedia.org',
						'frr' => 'http://frr.wikipedia.org',
						'fur' => 'http://fur.wikipedia.org',
						'fy' => 'http://fy.wikipedia.org',
						'ga' => 'http://ga.wikipedia.org',
						'gd' => 'http://gd.wikipedia.org',
						'gl' => 'http://gl.wikipedia.org',
						'glk' => 'http://glk.wikipedia.org',
						'gn' => 'http://gn.wikipedia.org',
						'got' => 'http://got.wikipedia.org',
						'gu' => 'http://gu.wikipedia.org',
						'gv' => 'http://gv.wikipedia.org',
						'ha' => 'http://ha.wikipedia.org',
						'hak' => 'http://hak.wikipedia.org',
						'haw' => 'http://haw.wikipedia.org',
						'he' => 'http://he.wikipedia.org',
						'hi' => 'http://hi.wikipedia.org',
						'ho' => 'http://ho.wikipedia.org',
						'hr' => 'http://hr.wikipedia.org',
						'hsb' => 'http://hsb.wikipedia.org',
						'ht' => 'http://ht.wikipedia.org',
						'hu' => 'http://hu.wikipedia.org',
						'hy' => 'http://hy.wikipedia.org',
						'hz' => 'http://hz.wikipedia.org',
						'ia' => 'http://ia.wikipedia.org',
						'id' => 'http://id.wikipedia.org',
						'ie' => 'http://ie.wikipedia.org',
						'ig' => 'http://ig.wikipedia.org',
						'ii' => 'http://ii.wikipedia.org',
						'ik' => 'http://ik.wikipedia.org',
						'ilo' => 'http://ilo.wikipedia.org',
						'io' => 'http://io.wikipedia.org',
						'is' => 'http://is.wikipedia.org',
						'it' => 'http://it.wikipedia.org',
						'iu' => 'http://iu.wikipedia.org',
						'ja' => 'http://ja.wikipedia.org',
						'jbo' => 'http://jbo.wikipedia.org',
						'jv' => 'http://jv.wikipedia.org',
						'ka' => 'http://ka.wikipedia.org',
						'kab' => 'http://kab.wikipedia.org',
						'kg' => 'http://kg.wikipedia.org',
						'ki' => 'http://ki.wikipedia.org',
						'kj' => 'http://kj.wikipedia.org',
						'kk' => 'http://kk.wikipedia.org',
						'kl' => 'http://kl.wikipedia.org',
						'km' => 'http://km.wikipedia.org',
						'kn' => 'http://kn.wikipedia.org',
						'ko' => 'http://ko.wikipedia.org',
						'kr' => 'http://kr.wikipedia.org',
						'ks' => 'http://ks.wikipedia.org',
						'ksh' => 'http://ksh.wikipedia.org',
						'ku' => 'http://ku.wikipedia.org',
						'kv' => 'http://kv.wikipedia.org',
						'kw' => 'http://kw.wikipedia.org',
						'ky' => 'http://ky.wikipedia.org',
						'la' => 'http://la.wikipedia.org',
						'lad' => 'http://lad.wikipedia.org',
						'lb' => 'http://lb.wikipedia.org',
						'lbe' => 'http://lbe.wikipedia.org',
						'lez' => 'http://lez.wikipedia.org',
						'lg' => 'http://lg.wikipedia.org',
						'li' => 'http://li.wikipedia.org',
						'lij' => 'http://lij.wikipedia.org',
						'lmo' => 'http://lmo.wikipedia.org',
						'ln' => 'http://ln.wikipedia.org',
						'lo' => 'http://lo.wikipedia.org',
						'lt' => 'http://lt.wikipedia.org',
						'lv' => 'http://lv.wikipedia.org',
						'map-bms' => 'http://map-bms.wikipedia.org',
						'mg' => 'http://mg.wikipedia.org',
						'mh' => 'http://mh.wikipedia.org',
						'mi' => 'http://mi.wikipedia.org',
						'mk' => 'http://mk.wikipedia.org',
						'ml' => 'http://ml.wikipedia.org',
						'mn' => 'http://mn.wikipedia.org',
						'mr' => 'http://mr.wikipedia.org',
						'ms' => 'http://ms.wikipedia.org',
						'mt' => 'http://mt.wikipedia.org',
						'mus' => 'http://mus.wikipedia.org',
						'mwl' => 'http://mwl.wikipedia.org',
						'my' => 'http://my.wikipedia.org',
						'mzn' => 'http://mzn.wikipedia.org',
						'na' => 'http://na.wikipedia.org',
						'nah' => 'http://nah.wikipedia.org',
						'nap' => 'http://nap.wikipedia.org',
						'nds' => 'http://nds.wikipedia.org',
						'nds-nl' => 'http://nds-nl.wikipedia.org',
						'ne' => 'http://ne.wikipedia.org',
						'new' => 'http://new.wikipedia.org',
						'ng' => 'http://ng.wikipedia.org',
						'nl' => 'http://nl.wikipedia.org',
						'nn' => 'http://nn.wikipedia.org',
						'no' => 'http://no.wikipedia.org',
						'nov' => 'http://nov.wikipedia.org',
						'nrm' => 'http://nrm.wikipedia.org',
						'nv' => 'http://nv.wikipedia.org',
						'ny' => 'http://ny.wikipedia.org',
						'oc' => 'http://oc.wikipedia.org',
						'om' => 'http://om.wikipedia.org',
						'or' => 'http://or.wikipedia.org',
						'os' => 'http://os.wikipedia.org',
						'pa' => 'http://pa.wikipedia.org',
						'pag' => 'http://pag.wikipedia.org',
						'pam' => 'http://pam.wikipedia.org',
						'pap' => 'http://pap.wikipedia.org',
						'pcd' => 'http://pcd.wikipedia.org',
						'pdc' => 'http://pdc.wikipedia.org',
						'pi' => 'http://pi.wikipedia.org',
						'pih' => 'http://pih.wikipedia.org',
						'pl' => 'http://pl.wikipedia.org',
						'pms' => 'http://pms.wikipedia.org',
						'pnb' => 'http://pnb.wikipedia.org',
						'pnt' => 'http://pnt.wikipedia.org',
						'ps' => 'http://ps.wikipedia.org',
						'pt' => 'http://pt.wikipedia.org',
						'qu' => 'http://qu.wikipedia.org',
						'rm' => 'http://rm.wikipedia.org',
						'rmy' => 'http://rmy.wikipedia.org',
						'rn' => 'http://rn.wikipedia.org',
						'ro' => 'http://ro.wikipedia.org',
						'roa-rup' => 'http://roa-rup.wikipedia.org',
						'roa-tara' => 'http://roa-tara.wikipedia.org',
						'ru' => 'http://ru.wikipedia.org',
						'rw' => 'http://rw.wikipedia.org',
						'sa' => 'http://sa.wikipedia.org',
						'sah' => 'http://sah.wikipedia.org',
						'sc' => 'http://sc.wikipedia.org',
						'scn' => 'http://scn.wikipedia.org',
						'sco' => 'http://sco.wikipedia.org',
						'sd' => 'http://sd.wikipedia.org',
						'se' => 'http://se.wikipedia.org',
						'sg' => 'http://sg.wikipedia.org',
						'sh' => 'http://sh.wikipedia.org',
						'si' => 'http://si.wikipedia.org',
						'simple' => 'http://simple.wikipedia.org',
						'sk' => 'http://sk.wikipedia.org',
						'sl' => 'http://sl.wikipedia.org',
						'sm' => 'http://sm.wikipedia.org',
						'sn' => 'http://sn.wikipedia.org',
						'so' => 'http://so.wikipedia.org',
						'sq' => 'http://sq.wikipedia.org',
						'sr' => 'http://sr.wikipedia.org',
						'ss' => 'http://ss.wikipedia.org',
						'st' => 'http://st.wikipedia.org',
						'stq' => 'http://stq.wikipedia.org',
						'su' => 'http://su.wikipedia.org',
						'sv' => 'http://sv.wikipedia.org',
						'sw' => 'http://sw.wikipedia.org',
						'szl' => 'http://szl.wikipedia.org',
						'ta' => 'http://ta.wikipedia.org',
						'te' => 'http://te.wikipedia.org',
						'tet' => 'http://tet.wikipedia.org',
						'tg' => 'http://tg.wikipedia.org',
						'th' => 'http://th.wikipedia.org',
						'ti' => 'http://ti.wikipedia.org',
						'tk' => 'http://tk.wikipedia.org',
						'tl' => 'http://tl.wikipedia.org',
						'tn' => 'http://tn.wikipedia.org',
						'to' => 'http://to.wikipedia.org',
						'tpi' => 'http://tpi.wikipedia.org',
						'tr' => 'http://tr.wikipedia.org',
						'ts' => 'http://ts.wikipedia.org',
						'tt' => 'http://tt.wikipedia.org',
						'tum' => 'http://tum.wikipedia.org',
						'tw' => 'http://tw.wikipedia.org',
						'ty' => 'http://ty.wikipedia.org',
						'udm' => 'http://udm.wikipedia.org',
						'ug' => 'http://ug.wikipedia.org',
						'uk' => 'http://uk.wikipedia.org',
						'ur' => 'http://ur.wikipedia.org',
						'uz' => 'http://uz.wikipedia.org',
						've' => 'http://ve.wikipedia.org',
						'vec' => 'http://vec.wikipedia.org',
						'vi' => 'http://vi.wikipedia.org',
						'vls' => 'http://vls.wikipedia.org',
						'vo' => 'http://vo.wikipedia.org',
						'wa' => 'http://wa.wikipedia.org',
						'war' => 'http://war.wikipedia.org',
						'wo' => 'http://wo.wikipedia.org',
						'wuu' => 'http://wuu.wikipedia.org',
						'xal' => 'http://xal.wikipedia.org',
						'xh' => 'http://xh.wikipedia.org',
						'xmf' => 'http://xmf.wikipedia.org',
						'yi' => 'http://yi.wikipedia.org',
						'yo' => 'http://yo.wikipedia.org',
						'yue' => 'http://zh-yue.wikipedia.org',
						'za' => 'http://za.wikipedia.org',
						'zea' => 'http://zea.wikipedia.org',
						'zh' => 'http://zh.wikipedia.org',
						'zh-classical' => 'http://zh-classical.wikipedia.org',
						'zh-min-nan' => 'http://zh-min-nan.wikipedia.org',
						'zu' => 'http://zu.wikipedia.org',
						//'foobar' => array( 'url' => 'https://www.foo.bar/', 'filepath' => '/folder/', 'urlpath' => '/wikiname/$1' ),
					),
					'defaultSiteType' => 'mediawiki',
				),
				'stuff' => array(
					'sites' => array(
						'stuff-en' => 'https://en.wikipedia.org',
						'stuff-de' => 'https://de.wikipedia.org',
					),
					'defaultSiteUrlPath' => '/stuffwiki/$1',
					'defaultSiteFilePath' => '/somepath/$1',
				),
			),

			'defaultSiteUrlPath' => '/wiki/$1',
			'defaultSiteFilePath' => '/w/$1',
			'defaultSiteType' => 'unknown',
		);
	}

}
