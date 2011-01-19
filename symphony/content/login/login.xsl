<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml"
	doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
	doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"
	omit-xml-declaration="yes"
	encoding="UTF-8"
	indent="yes"/>

<xsl:template match="/">
	<html>
		<head>

			<title>Symphony Login</title>

			<!-- CSS -->
			<link rel="stylesheet" href="/symphony/assets/css/peripheral.css" media="screen,projection" type="text/css" />

		</head>

		<body>
			<form action="" method="post" class="panel">
				<h1>Sort of functional login</h1>
				<fieldset>
					<input name="username" type="text"/>
					<input name="password" type="password"/>
					<input type="submit" name="action[login]" value="login"/>
				</fieldset>
			</form>
		</body>
	</html>
</xsl:template>

</xsl:stylesheet>