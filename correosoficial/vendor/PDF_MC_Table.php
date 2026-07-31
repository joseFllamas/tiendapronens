<?php

/**
 * Clase PDF_MC_Table
 * Extiende FPDF para añadir funcionalidad de tablas con multi-celdas
 */
class PDF_MC_Table extends FPDF
{
    protected $widths;
    protected $aligns;

    /**
     * Establece los anchos de las columnas
     * @param array $w Array de anchos
     */
    public function SetWidths($w)
    {
        $this->widths = $w;
    }

    /**
     * Establece las alineaciones de las columnas
     * @param array $a Array de alineaciones
     */
    public function SetAligns($a)
    {
        $this->aligns = $a;
    }

    /**
     * Crea una fila de tabla con multi-celdas
     * @param array $data Array de datos para cada celda
     */
    public function Row($data)
    {
        // Calcula la altura de la fila
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 5 * $nb;
        
        // Realiza un salto de página si es necesario
        $this->CheckPageBreak($h);
        
        // Dibuja las celdas de la fila
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            
            // Guarda la posición actual
            $x = $this->GetX();
            $y = $this->GetY();
            
            // Dibuja el borde
            $this->Rect($x, $y, $w, $h);
            
            // Imprime el texto
            $this->MultiCell($w, 5, $data[$i], 0, $a);
            
            // Coloca la posición a la derecha de la celda
            $this->SetXY($x + $w, $y);
        }
        
        // Va a la siguiente línea
        $this->Ln($h);
    }

    /**
     * Verifica si se necesita un salto de página
     * @param float $h Altura necesaria
     * @param mixed $y Coordenada Y (opcional para compatibilidad con TCPDF)
     * @param bool $addpage Si se debe agregar una página (opcional para compatibilidad con TCPDF)
     * @return bool
     */
    protected function CheckPageBreak($h = 0, $y = '', $addpage = true)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            if ($addpage) {
                $this->AddPage($this->CurOrientation);
            }
            return true;
        }
        return false;
    }

    /**
     * Calcula el número de líneas que ocupará una MultiCell
     * @param float $w Ancho
     * @param string $txt Texto
     * @return int Número de líneas
     */
    protected function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += isset($cw[$c]) ? $cw[$c] : 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}
