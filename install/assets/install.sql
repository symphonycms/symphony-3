
--
-- Table structure for table `tbl_cache`
--

CREATE TABLE `tbl_cache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `creation` int(14) NOT NULL DEFAULT '0',
  `expiry` int(14) unsigned DEFAULT NULL,
  `data` longtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `creation` (`creation`),
  KEY `hash` (`hash`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_entries`
--

CREATE TABLE `tbl_entries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `section` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `creation_date` datetime NOT NULL,
  `creation_date_gmt` datetime NOT NULL,
  `modification_date` datetime NOT NULL,
  `modification_date_gmt` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `section_id` (`section`),
  KEY `author_id` (`user_id`),
  KEY `creation_date` (`creation_date`),
  KEY `creation_date_gmt` (`creation_date_gmt`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_extensions`
--

CREATE TABLE `tbl_extensions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `status` enum('enabled','disabled') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'enabled',
  `version` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_extensions_delegates`
--

CREATE TABLE `tbl_extensions_delegates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `extension_id` int(11) NOT NULL,
  `page` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `delegate` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `callback` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `extension_id` (`extension_id`),
  KEY `page` (`page`),
  KEY `delegate` (`delegate`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_forgotpass`
--

CREATE TABLE `tbl_forgotpass` (
  `user_id` int(11) NOT NULL DEFAULT '0',
  `token` varchar(6) COLLATE utf8_unicode_ci NOT NULL,
  `expiry` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sections_sync`
--

CREATE TABLE `tbl_sections_sync` (
  `section` varchar(32) NOT NULL DEFAULT '',
  `xml` text,
  PRIMARY KEY (`section`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sessions`
--

CREATE TABLE `tbl_sessions` (
  `session` varchar(255) CHARACTER SET utf8 NOT NULL,
  `session_expires` int(10) unsigned NOT NULL DEFAULT '0',
  `session_data` text CHARACTER SET utf8,
  PRIMARY KEY (`session`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `password` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `first_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_seen` datetime DEFAULT '0000-00-00 00:00:00',
  `default_section` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `auth_token_active` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
  `language` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


--
-- Table structure for table `tbl_data_articles_body`
--

CREATE TABLE `tbl_data_articles_body` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `handle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci,
  `value_formatted` text COLLATE utf8_unicode_ci,
  `word_count` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  FULLTEXT KEY `value` (`value`),
  FULLTEXT KEY `value_formatted` (`value_formatted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_articles_category`
--

CREATE TABLE `tbl_data_articles_category` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `handle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `handle` (`handle`),
  KEY `value` (`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_articles_date`
--

CREATE TABLE `tbl_data_articles_date` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `value` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `local` int(11) DEFAULT NULL,
  `gmt` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `value` (`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_articles_published`
--

CREATE TABLE `tbl_data_articles_published` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `value` enum('yes','no') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `value` (`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_articles_title`
--

CREATE TABLE `tbl_data_articles_title` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `handle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci,
  `value_formatted` text COLLATE utf8_unicode_ci,
  `word_count` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  FULLTEXT KEY `value` (`value`),
  FULLTEXT KEY `value_formatted` (`value_formatted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_categories_name`
--

CREATE TABLE `tbl_data_categories_name` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `handle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci,
  `value_formatted` text COLLATE utf8_unicode_ci,
  `word_count` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  FULLTEXT KEY `value` (`value`),
  FULLTEXT KEY `value_formatted` (`value_formatted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_comments_article`
--

CREATE TABLE `tbl_data_comments_article` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `relation_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `relation_id` (`relation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_comments_comment`
--

CREATE TABLE `tbl_data_comments_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `handle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci,
  `value_formatted` text COLLATE utf8_unicode_ci,
  `word_count` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  FULLTEXT KEY `value` (`value`),
  FULLTEXT KEY `value_formatted` (`value_formatted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_comments_date`
--

CREATE TABLE `tbl_data_comments_date` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `value` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `local` int(11) DEFAULT NULL,
  `gmt` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `value` (`value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_images_article`
--

CREATE TABLE `tbl_data_images_article` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `relation_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  KEY `relation_id` (`relation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_images_description`
--

CREATE TABLE `tbl_data_images_description` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `handle` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci,
  `value_formatted` text COLLATE utf8_unicode_ci,
  `word_count` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entry_id` (`entry_id`),
  FULLTEXT KEY `value` (`value`),
  FULLTEXT KEY `value_formatted` (`value_formatted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_data_images_image`
--

CREATE TABLE `tbl_data_images_image` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) unsigned NOT NULL,
  `name` text COLLATE utf8_unicode_ci,
  `path` text COLLATE utf8_unicode_ci,
  `file` text COLLATE utf8_unicode_ci,
  `size` int(11) unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `meta` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_id` (`entry_id`),
  FULLTEXT KEY `name` (`name`),
  FULLTEXT KEY `path` (`path`),
  FULLTEXT KEY `file` (`file`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------


INSERT INTO `tbl_extensions` (`id`, `name`, `status`, `version`) VALUES
(18, 'field_checkbox', 'enabled', '2.0.0'),
(19, 'field_date', 'enabled', '2.0.0'),
(21, 'field_selectbox', 'enabled', '2.0.0'),
(22, 'field_taglist', 'enabled', '2.0.0'),
(51, 'jit_image_manipulation', 'enabled', '1.08'),
(24, 'field_upload', 'enabled', '2.0.0'),
(25, 'field_user', 'enabled', '2.0.0'),
(26, 'ds_dynamicxml', 'enabled', '1.0.0'),
(52, 'maintenance_mode', 'enabled', '1.2'),
(27, 'ds_navigation', 'enabled', '1.0.0'),
(28, 'ds_sections', 'enabled', '1.0.0'),
(29, 'ds_staticxml', 'enabled', '1.0.0'),
(30, 'ds_users', 'enabled', '1.0.0'),
(33, 'debugdevkit', 'enabled', '1.0.5'),
(31, 'profiledevkit', 'enabled', '1.0.1'),
(32, 'markdown', 'enabled', '1.10'),
(34, 'field_selectboxlink', 'enabled', '2.0.0'),
(35, 'field_textbox', 'enabled', '2.0.17'),
(53, 'field_link', 'enabled', '2.0.0'),
(54, 'devkit_debug', 'enabled', '2.0'),
(55, 'event_sections', 'enabled', '1.0.0');

--
-- Dumping data for table `tbl_extensions_delegates`
--

INSERT INTO `tbl_extensions_delegates` (`id`, `extension_id`, `page`, `delegate`, `callback`) VALUES
(NULL, 54, '/frontend/', 'FrontendPreInitialise', 'frontendPreInitialise'),
(NULL, 52, '/frontend/', 'FrontendParamsResolve', '__addParam'),
(NULL, 52, '/backend/', 'AppendPageAlert', '__appendAlert'),
(NULL, 52, '/frontend/', 'FrontendPrePageResolve', '__checkForMaintenanceMode'),
(NULL, 52, '/system/settings/', 'CustomActions', '__toggleMaintenanceMode'),
(NULL, 27, '/backend/', 'DataSourceFormAction', 'action'),
(NULL, 27, '/backend/', 'DataSourceFormPrepare', 'prepare'),
(NULL, 33, '/frontend/', 'ManipulateDevKitNavigation', 'manipulateDevKitNavigation'),
(NULL, 33, '/frontend/', 'FrontendDevKitResolve', 'frontendDevKitResolve'),
(NULL, 31, '/frontend/', 'ManipulateDevKitNavigation', 'manipulateDevKitNavigation'),
(NULL, 31, '/frontend/', 'FrontendDevKitResolve', 'frontendDevKitResolve'),
(NULL, 27, '/backend/', 'DataSourceFormView', 'view'),
(NULL, 54, '/frontend/', 'FrontendPreRender', 'frontendPreRender'),
(NULL, 51, '/system/settings/extensions/', 'CustomSaveActions', 'cbSavePreferences'),
(NULL, 52, '/system/settings/extensions/', 'AddSettingsFieldsets', 'cbAppendPreferences'),
(NULL, 51, '/system/settings/extensions/', 'AddSettingsFieldsets', 'cbAppendPreferences'),
(NULL, 52, '/system/settings/extensions/', 'CustomSaveActions', 'cbSavePreferences'),
(NULL, 54, '/frontend/', 'DevKiAppendtMenuItem', 'appendDevKitMenuItem');

--
-- Dumping data for table `tbl_sections_sync`
--

INSERT INTO `tbl_sections_sync` (`section`, `xml`) VALUES
('4bbd2f242aa4b', '<?xml version="1.0" encoding="UTF-8"?>\n<section guid="4bbd2f242aa4b">\n  <name handle="articles">Articles</name>\n  <hidden-from-publish-menu>no</hidden-from-publish-menu>\n  <navigation-group>Content</navigation-group>\n  <publish-order-handle>title</publish-order-handle>\n  <publish-order-direction>desc</publish-order-direction>\n  <fields>\n    <field guid="5174_4bcfa45fd8674">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <size>medium</size>\n      <type>textbox</type>\n      <label>Title</label>\n      <text-size>single</text-size>\n      <text-formatter>markdown</text-formatter>\n      <text-validator></text-validator>\n      <text-length>255</text-length>\n      <column-length>75</column-length>\n      <text-handle>yes</text-handle>\n      <section>articles</section>\n      <element-name>title</element-name>\n    </field>\n    <field guid="5174_4bcfa45fd89b0">\n      <required>yes</required>\n      <show-column>no</show-column>\n      <size>medium</size>\n      <type>textbox</type>\n      <label>Body</label>\n      <text-size>medium</text-size>\n      <text-formatter>markdown_with_purifier</text-formatter>\n      <text-validator></text-validator>\n      <text-length>1024</text-length>\n      <column-length>75</column-length>\n      <section>articles</section>\n      <element-name>body</element-name>\n    </field>\n    <field guid="5fc7_4bcfa45fd8b05">\n      <required>no</required>\n      <show-column>yes</show-column>\n      <type>date</type>\n      <label>Date</label>\n      <pre-populate>yes</pre-populate>\n      <section>articles</section>\n      <element-name>date</element-name>\n    </field>\n    <field guid="9fce_4bcfa45fd8c40">\n      <required>no</required>\n      <show-column>yes</show-column>\n      <type>checkbox</type>\n      <label>Published</label>\n      <description>Publish this entry</description>\n      <default-state>on</default-state>\n      <section>articles</section>\n      <element-name>published</element-name>\n    </field>\n    <field guid="9993_4bcfa45fd8d33">\n      <required>no</required>\n      <show-column>yes</show-column>\n      <type>select</type>\n      <label>Category</label>\n      <static-options></static-options>\n      <dynamic-options>categories::name</dynamic-options>\n      <section>articles</section>\n      <element-name>category</element-name>\n    </field>\n  </fields>\n  <layout>\n    <column>\n      <size>large</size>\n      <fieldset>\n        <name>Essentials</name>\n        <field>title</field>\n        <field>body</field>\n      </fieldset>\n    </column>\n    <column>\n      <size>small</size>\n      <fieldset>\n        <name>Options</name>\n        <field>date</field>\n        <field>category</field>\n        <field>published</field>\n      </fieldset>\n    </column>\n  </layout>\n</section>\n'),
('4bd7c7dfbc84b', '<?xml version="1.0" encoding="UTF-8"?>\n<section guid="4bd7c7dfbc84b">\n  <name handle="comments">Comments</name>\n  <hidden-from-publish-menu>no</hidden-from-publish-menu>\n  <navigation-group>Publish</navigation-group>\n  <publish-order-handle></publish-order-handle>\n  <publish-order-direction></publish-order-direction>\n  <fields>\n    <field guid="4bd7c7dfbc9b5">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <size>medium</size>\n      <type>textbox</type>\n      <label>Comment</label>\n      <text-size>medium</text-size>\n      <text-formatter>markdown_with_purifier</text-formatter>\n      <text-validator></text-validator>\n      <text-length>1024</text-length>\n      <column-length>75</column-length>\n      <section>comments</section>\n      <element-name>comment</element-name>\n    </field>\n    <field guid="4bd7c809d4c4a">\n      <required>no</required>\n      <show-column>yes</show-column>\n      <type>date</type>\n      <label>Date</label>\n      <pre-populate>yes</pre-populate>\n      <section>comments</section>\n      <element-name>date</element-name>\n    </field>\n    <field guid="4bd7c7dfbcc77">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <limit>20</limit>\n      <type>link</type>\n      <label>Article</label>\n      <allow-multiple-selection>yes</allow-multiple-selection>\n      <section>comments</section>\n      <element-name>article</element-name>\n      <related-fields>\n        <item section="articles" field="title"/>\n      </related-fields>\n    </field>\n  </fields>\n  <layout>\n    <column>\n      <size>large</size>\n      <fieldset>\n        <name>Untitled</name>\n        <field>comment</field>\n      </fieldset>\n    </column>\n    <column>\n      <size>small</size>\n      <fieldset>\n        <name>Untitled</name>\n        <field>date</field>\n        <field>article</field>\n      </fieldset>\n    </column>\n  </layout>\n</section>\n'),
('4bcff8fe2f363', '<?xml version="1.0" encoding="UTF-8"?>\n<section guid="4bcff8fe2f363">\n  <name handle="categories">Categories</name>\n  <hidden-from-publish-menu>no</hidden-from-publish-menu>\n  <navigation-group>Content</navigation-group>\n  <publish-order-handle></publish-order-handle>\n  <publish-order-direction></publish-order-direction>\n  <fields>\n    <field guid="4bcff8fe2f576">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <size>medium</size>\n      <type>textbox</type>\n      <label>Name</label>\n      <text-size>single</text-size>\n      <text-formatter></text-formatter>\n      <text-validator></text-validator>\n      <text-length>55</text-length>\n      <column-length>55</column-length>\n      <text-handle>yes</text-handle>\n      <section>categories</section>\n      <element-name>name</element-name>\n    </field>\n  </fields>\n  <layout>\n    <column>\n      <size>large</size>\n      <fieldset>\n        <name>Essentials</name>\n        <field>name</field>\n      </fieldset>\n    </column>\n  </layout>\n</section>\n'),
('4bd69d1704be1', '<?xml version="1.0" encoding="UTF-8"?>\n<section guid="4bd69d1704be1">\n  <name handle="comments">Comments</name>\n  <hidden-from-publish-menu>no</hidden-from-publish-menu>\n  <navigation-group>Content</navigation-group>\n  <publish-order-handle></publish-order-handle>\n  <publish-order-direction></publish-order-direction>\n  <fields>\n    <field guid="4bd69d01cc37b">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <size>medium</size>\n      <type>textbox</type>\n      <label>Comment</label>\n      <text-size>medium</text-size>\n      <text-formatter>markdown_with_purifier</text-formatter>\n      <text-validator></text-validator>\n      <text-length></text-length>\n      <column-length>75</column-length>\n      <text-handle>yes</text-handle>\n      <section>comments</section>\n      <element-name>comment</element-name>\n    </field>\n    <field guid="4bd69d01cc49b">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <limit>24</limit>\n      <type>link</type>\n      <label>Article</label>\n      <allow-multiple-selection>yes</allow-multiple-selection>\n      <section>comments</section>\n      <element-name>article</element-name>\n      <related-fields>\n        <item section="articles" field="title"/>\n        <item section="categories" field="name"/>\n      </related-fields>\n    </field>\n  </fields>\n  <layout>\n    <column>\n      <size>large</size>\n      <fieldset>\n        <name>Untitled</name>\n        <field>comment</field>\n      </fieldset>\n    </column>\n    <column>\n      <size>small</size>\n      <fieldset>\n        <name>Untitled</name>\n        <field>article</field>\n      </fieldset>\n    </column>\n  </layout>\n</section>\n'),
('4be3e4995771d', '<?xml version="1.0" encoding="UTF-8"?>\n<section guid="4be3e4995771d">\n  <name handle="images">Images</name>\n  <hidden-from-publish-menu>no</hidden-from-publish-menu>\n  <navigation-group>Content</navigation-group>\n  <publish-order-handle></publish-order-handle>\n  <publish-order-direction></publish-order-direction>\n  <fields>\n    <field guid="4be3e4995785a">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <type>upload</type>\n      <label>Image</label>\n      <destination>/workspace/uploads</destination>\n      <validator>/\\\\\\\\.(?:bmp|gif|jpe?g|png)$/i</validator>\n      <serialise>yes</serialise>\n      <section>images</section>\n      <element-name>image</element-name>\n    </field>\n    <field guid="4be3e49957aab">\n      <required>yes</required>\n      <show-column>yes</show-column>\n      <limit>20</limit>\n      <type>link</type>\n      <label>Article</label>\n      <section>images</section>\n      <element-name>article</element-name>\n      <related-fields>\n        <item section="articles" field="title"/>\n      </related-fields>\n    </field>\n    <field guid="4be3e49957cdb">\n      <required>no</required>\n      <show-column>yes</show-column>\n      <size>medium</size>\n      <type>textbox</type>\n      <label>Description</label>\n      <text-size>small</text-size>\n      <text-formatter>markdown</text-formatter>\n      <text-validator></text-validator>\n      <text-length>255</text-length>\n      <column-length>75</column-length>\n      <text-handle>yes</text-handle>\n      <section>images</section>\n      <element-name>description</element-name>\n    </field>\n  </fields>\n  <layout>\n    <column>\n      <size>large</size>\n      <fieldset>\n        <name>Untitled</name>\n        <field>image</field>\n        <field>description</field>\n      </fieldset>\n    </column>\n    <column>\n      <size>small</size>\n      <fieldset>\n        <name>Untitled</name>\n        <field>article</field>\n      </fieldset>\n    </column>\n  </layout>\n</section>\n');
