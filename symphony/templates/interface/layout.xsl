<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml"
	doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
	doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"
	omit-xml-declaration="yes"
	encoding="UTF-8"
	indent="yes" />
	
	<xsl:variable name="site-url" select="/root/context/system/site-url" />
	<xsl:variable name="admin-url" select="/root/context/system/admin-url" />

	<xsl:template match="root">
		<html>
			<head>
				<link rel="stylesheet" href="/symphony/assets/styles/admin.css" media="screen" type="text/css" />
	
				<!-- JavaScript -->
				<script src="/symphony/assets/scripts/jquery.js" type="text/javascript"></script>
				<script src="/symphony/assets/scripts/drawer.js" type="text/javascript"></script>
			</head>
			<body>
				<div id="control">
					<p id="sitename"><a href="">My Website</a></p>
					<p id="powered">Symphony 3.0 alpha</p>
	
					<xsl:apply-templates select="navigation"/>
				</div>
				<div id="drawer">
					<h2>About this Shit</h2>
					<p>All kinds of awesome stuff will go here</p>
				</div>
				<div id="view">
					<h1><xsl:value-of select="context/view/title"/></h1>
					<xsl:apply-templates select="actions"/>
					<xsl:apply-templates select="." mode="view"/>
				</div>
			</body>
		</html>
	</xsl:template>
	
	<xsl:template match="navigation">
		<ul id="nav">
			<xsl:apply-templates select="group"/>
		</ul>
	</xsl:template>
	
	<xsl:template match="navigation/group/items/item">
		<li id="{name/@handle}">
			<a href="{$admin-url}/{link}">
				<xsl:value-of select="name"/>
			</a>
			<a href="{$admin-url}/{link}/new/" class="quick create">New</a>
		</li>
	</xsl:template>
	
	<xsl:template match="navigation/group">
		<li id="{name/@handle}">
			<!-- Temporarily wrap in SPAN just so it'll nest neatly :P -->
			<span>
				<xsl:value-of select="name"/>
			</span>
			<a href="#" class="toggle">
				<xsl:text>&#9662;</xsl:text>
			</a>
			<xsl:if test="items">
				<ul>
					<xsl:apply-templates select="items/item"/>
				</ul>
			</xsl:if>
		</li>
	</xsl:template>
	
	<xsl:template match="actions">
		<ul id="actions">
			<xsl:apply-templates select="action"/>
		</ul>
	</xsl:template>
	
	<xsl:template match="actions/action">
		<li>
			<a href="{callback}" class="{type}"><xsl:value-of select="name"/></a>
		</li>
	</xsl:template>
</xsl:stylesheet>
