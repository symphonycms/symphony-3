<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:template match="* | text()" mode="debug-indent">
		<xsl:text>&#10;</xsl:text>
		
		<xsl:for-each select="ancestor::*">
			<xsl:text>&#09;</xsl:text>
		</xsl:for-each>
	</xsl:template>
	
	<xsl:template match="*" mode="debug">
		<xsl:apply-templates select="." mode="debug-indent" />
		
		<xsl:text>&lt;</xsl:text>
		<xsl:value-of select="name()" />
		<xsl:apply-templates select="@*" mode="debug" />
		
		<xsl:choose>
			<xsl:when test="* | text()">
				<xsl:text>&gt;</xsl:text>
				
				<xsl:apply-templates select="* | text()" mode="debug" />
				<xsl:apply-templates select="." mode="debug-indent" />
				
				<xsl:text>&lt;/</xsl:text>
				<xsl:value-of select="name()" />
				<xsl:text>&gt;</xsl:text>
			</xsl:when>
			<xsl:otherwise>
				<xsl:text> /&gt;</xsl:text>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template match="@*" mode="debug">
		<xsl:text> </xsl:text>
		<xsl:value-of select="name()" />
		<xsl:text>="</xsl:text>
		<xsl:value-of select="." />
		<xsl:text>"</xsl:text>
	</xsl:template>
	
	<xsl:template match="text()" mode="debug">
		<xsl:apply-templates select="." mode="debug-indent" />
		
		<xsl:value-of select="." />
	</xsl:template>
</xsl:stylesheet>