<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml"
	doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
	doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"
	omit-xml-declaration="yes"
	encoding="UTF-8"
	indent="yes" />

<xsl:template match="/">
	<html>
		<head>
			<link rel="stylesheet" href="/symphony/assets/styles/default.css" media="screen" type="text/css" />

			<!-- JavaScript -->
			<script src="/symphony/assets/scripts/jquery.js" type="text/javascript"></script>
			<script src="/symphony/assets/default/scripts/drawer.js" type="text/javascript"></script>
		</head>
		<body>
			<div id="control">
				<p id="sitename"><a href="">My Website</a></p>
				<p id="powered">Symphony 3.0 alpha</p>

				<xsl:apply-templates select="//navigation"/>
			</div>
			<div id="drawer">
				<h2>About this Shit</h2>
				<p>All kinds of awesome stuff will go here</p>
			</div>
			<div id="view">
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
		<a href="/{../name/@handle}/{link}">
			<xsl:value-of select="name"/>
		</a>
		<a href="{../name/@handle}/{link}/new/" class="quick create">New</a>
	</li>
</xsl:template>

<xsl:template match="navigation/group">
	<li id="{name/@handle}">
		<xsl:value-of select="name"/>
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

</xsl:stylesheet>
