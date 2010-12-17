<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:import href="layout.xsl"/>

<xsl:template match="root" mode="view">
	<xsl:copy-of select="."/>
</xsl:template>

</xsl:stylesheet>