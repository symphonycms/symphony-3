<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:import href="layout.xsl"/>
	
<!--
	HTML5
-->
	<xsl:template match="/root">
		<html>
			<head>
				<link rel="stylesheet" href="{$admin-url}/html5/admin.css" media="screen" type="text/css" />
				
				<!-- JavaScript -->
				<script src="{$admin-url}/assets/scripts/jquery.js" type="text/javascript"></script>
				<script src="{$admin-url}/html5/admin.js" type="text/javascript"></script>
			</head>
			<body>
				<header>
					<hgroup>
						<h1>
							<a href="">
								<xsl:value-of select="context/system/site-name" />
							</a>
						</h1>
						<p class="symphony-version">
							<xsl:value-of select="context/system/symphony-version" />
						</p>
					</hgroup>
					
					<xsl:apply-templates select="navigation"/>
				</header>
				<aside>
					<h2>About this Shit</h2>
					<p>All kinds of awesome stuff will go here</p>
				</aside>
				<section>
					<header>
						<xsl:apply-templates select="." mode="breadcrumb" />
						<xsl:apply-templates select="actions" />
					</header>
					
					<xsl:apply-templates select="." mode="view" />
				</section>
			</body>
		</html>
	</xsl:template>
	
<!--
	Breadcrumb
-->
	<xsl:template match="root" mode="breadcrumb">
		<nav class="breadcrumb">
			<ol>
				<li>
					<a href="">
						<xsl:value-of select="context/view/title" />
					</a>
				</li>
				<li>
					<a href="">This is a test</a>
				</li>
				<li>
					Editing some entry
				</li>
			</ol>
		</nav>
	</xsl:template>
	
<!--
	Actions
-->
	<xsl:template match="actions">
		<nav class="controls">
			<ul>
				<xsl:apply-templates select="action"/>
			</ul>
		</nav>
	</xsl:template>
	
	<xsl:template match="actions/action">
		<li>
			<a href="{callback}" class="{type}"><xsl:value-of select="name"/></a>
		</li>
	</xsl:template>
	
<!--
	Navigation
-->
	<xsl:template match="navigation">
		<nav>
			<ul>
				<xsl:apply-templates select="group"/>
			</ul>
		</nav>
	</xsl:template>
	
	<xsl:template match="navigation/group">
		<li id="{@handle}" data-group-handle="{@handle}">
			<!-- Temporarily wrap in SPAN just so it'll nest neatly :P -->
			<a>
				<xsl:value-of select="@name"/>
			</a>
			
			<xsl:if test="item[@visible = 'yes']">
				<ul>
					<xsl:apply-templates select="item" />
				</ul>
			</xsl:if>
		</li>
	</xsl:template>
	
	<xsl:template match="navigation/group//item" />
	
	<xsl:template match="navigation/group/item[@visible = 'yes']">
		<li id="{name/@handle}">
			<a href="{$admin-url}/{@link}">
				<xsl:if test="@active = 'yes' or .//item/@active = 'yes'">
					<xsl:attribute name="class">current</xsl:attribute>
				</xsl:if>
				
				<xsl:value-of select="@name"/>
			</a>
			
			<xsl:apply-templates select="item" />
		</li>
	</xsl:template>
	
	<xsl:template match="navigation/group/item/item[@visible = 'yes']">
		<a href="{$admin-url}/{@link}" class="quick create">
			<xsl:value-of select="@name"/>
		</a>
	</xsl:template>
	
<!--
	View
-->
	<xsl:template match="root" mode="view">
		<form action="" method="POST" class="view-content">
			<table id="views-list">
				<thead>
					<tr>
						<th scope="col">Title</th>
						<th scope="col">
							<acronym title="Universal Resource Locator">URL</acronym>
						</th>
						<th scope="col">
							<acronym title="Universal Resource Locator">URL</acronym>
							<xsl:text> Parameters</xsl:text>
						</th>
						<th scope="col">Type</th>
					</tr>
				</thead>
				<tbody>
					<xsl:apply-templates select="data/views/view"/>
				</tbody>
			</table>
		</form>
	</xsl:template>
	
	<xsl:template match="views/view">
		<tr id="view-{@guid}">
			<td>
				<a title="{title}" href="{/root/context/site-url}/symphony/framework/views/edit/{title/@handle}">
					<xsl:value-of select="title"/>
				</a>
				<input name="items[{title/@handle}]" type="checkbox"/>
			</td>
			<td>
				<a href="{/root/context/system/site-url}/{path}">
					<xsl:value-of select="/root/context/system/site-url"/>
					<xsl:text>/</xsl:text>
					<xsl:value-of select="path"/>
				</a>
			</td>
			<td>
				<xsl:choose>
					<xsl:when test="not(url-params)">
						<xsl:attribute name="class">inactive</xsl:attribute>
						<xsl:text>None</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:for-each select="url-params/item">
							<xsl:value-of select="."/>
							<xsl:text>/</xsl:text>
						</xsl:for-each>
					</xsl:otherwise>
				</xsl:choose>
			</td>
			<td>
				<xsl:choose>
					<xsl:when test="not(types)">
						<xsl:attribute name="class">inactive</xsl:attribute>
						<xsl:text>None</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:for-each select="types/item">
							<xsl:value-of select="."/>
							<xsl:if test="position() != last()">
								<xsl:text>, </xsl:text>
							</xsl:if>
						</xsl:for-each>
					</xsl:otherwise>
				</xsl:choose>
			</td>
		</tr>
	</xsl:template>
</xsl:stylesheet>