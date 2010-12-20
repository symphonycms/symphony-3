<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:import href="layout.xsl"/>

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
			<a title="{title}" href="{/root/context/site-url}/symphony/framework/views/edit/{handle}">
				<xsl:value-of select="title"/>
			</a>
			<input name="items[{handle}]" type="checkbox"/>
		</td>
		<td>
			<a href="{url}">
				<xsl:value-of select="url"/>
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