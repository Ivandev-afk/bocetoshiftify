<?xml version="1.0" encoding="UTF-8"?>
<!-- =========================================================
     turnos.xsl
     Hoja de transformación XSLT 1.0: convierte turnos.xml
     en el reporte HTML de la fila de atención de Shiftify.
     ========================================================= -->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <!-- El resultado será HTML, con sangría para que sea legible -->
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <!-- Plantilla principal: se aplica al elemento raíz -->
  <xsl:template match="/turnos">
    <html>
      <head>
        <title>Fila de atención <xsl:value-of select="@sede"/> | Shiftify</title>
        <!-- Colores tomados de la paleta de assets/css/tokens.css -->
        <style>
          body  { font-family: Arial, sans-serif; margin: 24px; background: #F8FAFC; color: #111827; }
          h1    { color: #0F172A; }
          table { border-collapse: collapse; background: #FFFFFF; }
          th, td { border: 1px solid #E5E7EB; padding: 6px 10px; }
          th    { background: #0F172A; color: #FFFFFF; }
          .demora { background: #FEF3C7; }
          .ahora  { font-weight: bold; color: #1D4ED8; }
        </style>
      </head>
      <body>
        <!-- Valores leídos de los atributos del elemento raíz -->
        <h1>Fila de atención · <xsl:value-of select="@sede"/></h1>
        <p>Fecha del reporte: <xsl:value-of select="@fecha"/></p>

        <table>
          <tr>
            <th>Código</th><th>Cliente</th><th>Hora</th>
            <th>Estado</th><th>Espera (min)</th><th>Atención (min)</th>
          </tr>

          <!-- Recorre cada turno, en orden de llegada (hora) -->
          <xsl:for-each select="turno">
            <xsl:sort select="hora"/>
            <tr>
              <!-- Si esperó más de 10 minutos, la fila se marca en ámbar -->
              <xsl:if test="espera &gt; 10">
                <xsl:attribute name="class">demora</xsl:attribute>
              </xsl:if>
              <td><xsl:value-of select="codigo"/></td>
              <td><xsl:value-of select="cliente"/></td>
              <td><xsl:value-of select="hora"/></td>
              <td>
                <!-- xsl:choose traduce el estado a un texto para el usuario -->
                <xsl:choose>
                  <xsl:when test="@estado = 'atendiendo'">
                    <span class="ahora">En ventanilla</span>
                  </xsl:when>
                  <xsl:when test="@estado = 'esperando'">En la fila</xsl:when>
                  <xsl:otherwise>Atendido</xsl:otherwise>
                </xsl:choose>
              </td>
              <td><xsl:value-of select="espera"/></td>
              <td>
                <xsl:choose>
                  <xsl:when test="duracion">
                    <xsl:value-of select="duracion"/>
                  </xsl:when>
                  <xsl:otherwise>—</xsl:otherwise>
                </xsl:choose>
              </td>
            </tr>
          </xsl:for-each>
        </table>

        <!-- Funciones XPath usadas dentro de XSLT -->
        <p>Turnos del día: <xsl:value-of select="count(turno)"/></p>
        <p>Atendidos: <xsl:value-of select="count(turno[@estado='atendido'])"/>
           · En la fila: <xsl:value-of select="count(turno[@estado='esperando'])"/></p>
        <p>Minutos de atención acumulados: <xsl:value-of select="sum(turno/duracion)"/></p>
        <p>Atención promedio: <xsl:value-of select="round(sum(turno/duracion) div count(turno/duracion))"/> min</p>
        <p>Filas en ámbar: turnos que esperaron más de 10 minutos.</p>
      </body>
    </html>
  </xsl:template>

</xsl:stylesheet>
