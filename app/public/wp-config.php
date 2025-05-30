<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'L)Ud_iL!mTTrqWqVc*kVk9X=:L1;`ruVs%;0)T@dZt~973uhSb-V33_+Ji.L/Z[!' );
define( 'SECURE_AUTH_KEY',   'b!@m`QupMV+12cnreavhfAfVy3-wUk[ehFHDN7Y|tjq`/Fz GKa<hWEL;JQM_y(~' );
define( 'LOGGED_IN_KEY',     '#!<$bJV?v_TF{NR!0}kpwO/Ldo=Gtbw(zR)g]W5q$#u=eaisVuwSrY/46o}dOT29' );
define( 'NONCE_KEY',         'RQe.E@Zz<5 t4L,itDxOj(ux;O+Dh>$Bvwu;:=Pp{SKSf&URo7+R`eD|;^$2TI<<' );
define( 'AUTH_SALT',         '-N3t!ijjVox4H6of:jH:<L#tISyumrE|%[5_M@S>9kXJNS^fH*gA|Lhj-/+?o+f2' );
define( 'SECURE_AUTH_SALT',  'y>K{1wdXqhS#Hq6;2O[b#+Jd]RhQ2JURVA-<Ke<gmwJO)v6Tbq=hv.(OhvV<jS5b' );
define( 'LOGGED_IN_SALT',    'KKN5j$HD-4]Ukdnk22dBk6]`M&{o(wk9PYEp/Ep,,Xdp7gYY~>wA>C,1I|bBWHU:' );
define( 'NONCE_SALT',        'w*@4//a{;{3W$,DK(?<Y/Y*KncC+`q!h) @u5v:^C&-KT, (0vpF8p^?p]ebvt}Z' );
define( 'WP_CACHE_KEY_SALT', 'j -{uwIAzZ~k9QAZ<u&z9EO*sI)HJ-{%dQ{o!cm7m(V2yV}}iIPO+lO>A{zJOX1P' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
