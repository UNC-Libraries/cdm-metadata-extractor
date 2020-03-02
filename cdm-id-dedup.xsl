<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema"
    exclude-result-prefixes="xs"
    version="2.0">
    <xsl:template match="/">
        <xsl:copy-of select="/*/record
            [index-of(/*/record/cdmid, 
            cdmid
            )
            [1]
            ]">
        </xsl:copy-of>
    </xsl:template>
</xsl:stylesheet>