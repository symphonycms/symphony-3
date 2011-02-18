<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="root" mode="view">
	<form action="" method="POST" class="view-content">
		<table id="entries-list">
			<thead>
				<tr>
					<xsl:for-each select="data/section/fields/field">
						<th scope="col"><xsl:value-of select="."/></th>
					</xsl:for-each>
				</tr>
			</thead>
			<tbody>
				<xsl:apply-templates select="data/section/entries/entry"/>
			</tbody>
		</table>
	</form>
</xsl:template>

<xsl:template match="entries/entry">
	<tr id="entry-{@id}">
		<xsl:apply-templates select="fields/*"/>
	</tr>
</xsl:template>

<xsl:template match="entries/entry/fields/*[position() = 1]">
	<td>
		<a href="/symphony/publish/{../../../../@handle}/edit/{../../@id}">
			<xsl:value-of select="."/>
		</a>
	</td>
</xsl:template>

<xsl:template match="entries/entry/fields/*[position() != 1]">
	<td><xsl:value-of select="."/></td>
</xsl:template>

</xsl:stylesheet>
