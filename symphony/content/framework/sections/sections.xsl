<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:import href="layout.xsl"/>

<xsl:template match="root" mode="view">
	<form action="" method="POST" class="view-content">
		<table id="sections-list">
			<thead>
				<tr>
					<th scope="col">Name</th>
					<th scope="col">Entries</th>
					<th scope="col">Navigation Group</th>
					<th scope="col">Status</th>
				</tr>
			</thead>
			<tbody>
				<xsl:apply-templates select="data/sections/section"/>
			</tbody>
		</table>
	</form>
</xsl:template>

<xsl:template match="sections/section">
	<tr id="section-{name/@handle}">
		<td>
			<a title="{name}" href="{/root/context/site-url}/symphony/framework/sections/edit/{name/@handle}">
				<xsl:value-of select="name"/>
			</a>
			<input name="items[{name/@handle}]" type="checkbox"/>
		</td>
		<td>
			<a href="{/root/context/system/site-url}/symphony/publish/{name/@handle}">
				<xsl:value-of select="@entries"/>
			</a>
		</td>
		<td>
			<xsl:value-of select="navigation-group"/>
		</td>
		<td>
			<xsl:value-of select="status"/>
		</td>
	</tr>
</xsl:template>

</xsl:stylesheet>