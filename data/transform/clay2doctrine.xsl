<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:output method="xml" indent="yes"/>

<xsl:template match='clay-model'>
  <xsl:apply-templates select="*"/>
</xsl:template>

<xsl:template match='database-model'>
  <xsl:variable name="beginScript" select='@begin-script'/>
  <xsl:variable name="dbName" select='@name'/>
  <database defaultIdMethod="native" defaultPhpNamingMethod="phpname">
    <xsl:attribute name='beginScript'><xsl:value-of select="$beginScript"/></xsl:attribute>
    <xsl:attribute name='name'><xsl:value-of select="$dbName"/></xsl:attribute>
    <xsl:for-each select="schema-list/schema/table-list">
      <xsl:apply-templates select="table"/>
    </xsl:for-each>
  </database>
</xsl:template>


<xsl:template match="table">
  <xsl:variable name="tableName" select='@name'/>
  <table>
    <xsl:attribute name='name'><xsl:value-of select="$tableName"/></xsl:attribute>
    <xsl:attribute name='alias'><xsl:value-of select="@alias"/></xsl:attribute>
    <xsl:for-each select="column-list">
      <xsl:apply-templates select="column"/>
    </xsl:for-each>
    <xsl:for-each select="unique-key-list">
      <xsl:apply-templates select="unique-key"/>
    </xsl:for-each>
    <xsl:for-each select="foreign-key-list">
      <xsl:apply-templates select="foreign-key"/>
    </xsl:for-each>
  </table>
</xsl:template>

<xsl:template match="column">
  <xsl:variable name="columnName" select='@name'/>
  <xsl:variable name="dataType" select='data-type/@name'/>
  <column>
    <xsl:attribute name='name'><xsl:value-of select="$columnName"/></xsl:attribute>
    <xsl:choose>
      <xsl:when test="$dataType = 'BIGSERIAL'">
        <xsl:attribute name='type'>BIGINT</xsl:attribute>
        <xsl:attribute name='autoIncrement'>true</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'BOOL'">
        <xsl:attribute name='type'>BOOLEAN</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'BYTEA'">
        <xsl:attribute name='type'>BLOB</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'CHARACTER'">
        <xsl:attribute name='type'>CHAR</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'DOUBLE PRECISION'">
        <xsl:attribute name='type'>DOUBLE</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'DECIMAL'">
        <xsl:attribute name='type'>DECIMAL</xsl:attribute>
      </xsl:when>      
      <xsl:when test="$dataType = 'FLOAT4'">
        <xsl:attribute name='type'>FLOAT</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'INT'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'INT2'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'INT4'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'INT8'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'SERIAL'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
        <xsl:attribute name='autoIncrement'>true</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'SERIAL4'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
        <xsl:attribute name='autoIncrement'>true</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'SERIAL8'">
        <xsl:attribute name='type'>INTEGER</xsl:attribute>
        <xsl:attribute name='autoIncrement'>true</xsl:attribute>
      </xsl:when>
      <xsl:when test="$dataType = 'TEXT'">
        <xsl:attribute name='type'>LONGVARCHAR</xsl:attribute>
      </xsl:when>
      <xsl:otherwise>
        <xsl:attribute name='type'><xsl:value-of select="$dataType"/></xsl:attribute>
      </xsl:otherwise>
    </xsl:choose>
    <xsl:if test="@column-size &gt; 0 and $dataType != 'BOOL' and $dataType != 'BOOLEAN' and $dataType != 'INT' and $dataType != 'INT2' and $dataType != 'INT4' and $dataType != 'INT8' and $dataType != 'SERIAL' and $dataType != 'SERIAL4' and $dataType != 'SERIAL8'">
      <xsl:attribute name='size'><xsl:value-of select="@column-size"/></xsl:attribute>
    </xsl:if>
    <xsl:attribute name='required'><xsl:value-of select="@mandatory"/></xsl:attribute>
    <xsl:attribute name='autoIncrement'><xsl:value-of select="@auto-increment"/></xsl:attribute>
    <xsl:attribute name='decimalDigits'><xsl:value-of select="@decimal-digits"/></xsl:attribute>
   
    <xsl:if test="@default-value != ''">
      <xsl:attribute name='default'><xsl:value-of select="@default-value"/></xsl:attribute>
    </xsl:if>
    <xsl:if test="../../primary-key/primary-key-column/@name=$columnName">
      <xsl:attribute name='primaryKey'>true</xsl:attribute>
    </xsl:if>
  </column>
</xsl:template>

<xsl:template match="unique-key">
  <unique>
    <xsl:apply-templates select="unique-key-column"/>
  </unique>
</xsl:template>

<xsl:template match="unique-key-column">
  <unique-column>
    <xsl:attribute name='name'><xsl:value-of select="@name"/></xsl:attribute>
  </unique-column>
</xsl:template>

<xsl:template match="foreign-key">
  <foreign-key>
    <xsl:attribute name='alias'><xsl:value-of select="@alias"/></xsl:attribute>
    <xsl:attribute name='foreignTable'><xsl:value-of select="@referenced-table"/></xsl:attribute>
    <xsl:attribute name='sourceMultiplicity'><xsl:value-of select="@source-multiplicity"/></xsl:attribute>
    <xsl:attribute name='targetMultiplicity'><xsl:value-of select="@target-multiplicity"/></xsl:attribute>
    <xsl:attribute name='onDelete'><xsl:value-of select="@on-delete"/></xsl:attribute>
      <xsl:apply-templates select="foreign-key-column"/>
  </foreign-key>
</xsl:template>

<xsl:template match="foreign-key-column">
  <reference>
    <xsl:attribute name='local'><xsl:value-of select="@column-name"/></xsl:attribute>
    <xsl:attribute name='foreign'><xsl:value-of select="@referenced-key-column-name"/></xsl:attribute>
  </reference>
</xsl:template>

</xsl:stylesheet>