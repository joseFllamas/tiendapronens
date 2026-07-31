<?php

namespace CorreosOficial;

// Autoload de Composer para las dependencias modernas
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/PDF_MC_Table.php';

// Las constantes de etiquetas (LABEL_TYPE_*, CEX_LABEL_*) las define el plugin en
// classes/CorreosOficialConstants.php, cargado desde correosoficial.php.

use LogicException;
use PDF_MC_Table;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\FpdiException;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\Filter\FilterException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;
use setasign\Fpdi\PdfReader\PageBoundaries;
use setasign\Fpdi\PdfReader\PdfReaderException;

class PDFMerger extends Fpdi
{
    private $files;
    private $fpdi;
    private $thermal;
    private $format;

    public function __construct($label_type = null, $labelFormat = null)
    {
        parent::__construct();

        $this->format = $labelFormat;

        $this->thermal == false;
        if ($label_type == LABEL_TYPE_THERMAL) {
            $this->thermal = true;
        }
    }

    public function split_label($filepath)
    {
        $pdf = new Fpdi;
        $pagecount = $pdf->setSourceFile($filepath);
        $array_labels = array();
        for ($i = 1; $i <= $pagecount; $i++) {
            $new_pdf = new Fpdi;
            $new_pdf->setSourceFile($filepath);
            $page = $new_pdf->importPage($i);
            $size = $new_pdf->getTemplateSize($page);
            if ($size['width'] > $size['height']) {
                $new_pdf->AddPage('L', array($size['width'], $size['height'])); // Landscape
            } else {
                $new_pdf->AddPage('P', array($size['width'], $size['height'])); // Portrait
            }
            $new_pdf->useTemplate($page);
            $array_labels[$i] = base64_encode($new_pdf->Output('fichero_' . $i . '.pdf', 'S'));
        }
        return $array_labels;
    }

    /**
     * Realiza divisiónes en vertical según el formato de etiqueta
     * @param string $filepath ruta del fichero
     * @param integer $format formato de la etiqueta*
     * @param integer $bulks Bultos del pedido
     * @param integer $cuts cortes por página
     *
     * @return array array de etiquetas en base64
     */
    public function splitByFormat($filepath, $bulks, $format, $cuts)
    {

        $basePDF = new Fpdi;
        $pagecount = $basePDF->setSourceFile($filepath);

        $outputLabels = [];

        switch ($format) {
            case LABEL_FORMAT_3A4:

                // Labels offsets
                $labelsOffsets = [
                    0 => 0,
                    1 => -95,
                    2 => -191
                ];

                // Recorremos páginas
                for ($p = 1; $p <= $pagecount; $p++) {

                    for ($l = 0; $l < $cuts; $l++) {

                        $new_pdf = new Fpdi;
                        $new_pdf->setSourceFile($filepath);
                        $new_pdf->AddPage('L', [210, 95]);
                        $new_pdf->useTemplate($new_pdf->importPage($p, 'CropBox'), 0, $labelsOffsets[$l]);

                        $outputLabels[] = base64_encode($new_pdf->Output('S'));

                        // si hemos llegado al número de bultos devolvemos
                        if (count($outputLabels) == $bulks) {
                            return $outputLabels;
                        }

                    }
                }

                break;
        }

        return $outputLabels;

    }

    public function addPDF($filepath, $pages = 'all')
    {
        if (file_exists($filepath)) {
            if (strtolower($pages) != 'all') {
                $pages = $this->rewritePages($pages);
            }

            $this->files[] = array($filepath, $pages);
        } else {
            throw new LogicException("Could not locate PDF on '$filepath'");
        }

        return $this;
    }

    public function merge($outputmode = 'browser', $outputpath = 'newfile.pdf', $type = '', $position = '')
    {
        if (!isset($this->files) || !is_array($this->files)):
            throw new LogicException("No PDFs to merge.");
        endif;

        $fpdi = new $this($this->thermal, $this->format);

        $label = 1;
        if ($position != '') {
            $label = $position;
        }

        if ($type == LABEL_TYPE_ADHESIVE) {

            // Formato 3/A4
            if ($this->format == LABEL_FORMAT_3A4) {

                $fpdi->AddPage('P');

                // Posiciones de las etiquetas
                $labelsOffsets = [1 => 3, 2 => 98, 3 => 193];

                $labelsPageCount = (int) $position;
                $labelsCount = 1;
                $totalLabels = count($this->files);

                foreach ($this->files as $file) {
                    $fpdi->setSourceFile($file[0]);
                    $fpdi->useTemplate($fpdi->importPage(1, 'CropBox'), 0, $labelsOffsets[$labelsPageCount]);

                    // Nueva página
                    if ($labelsPageCount === 3 && $labelsCount < $totalLabels) {
                        $fpdi->AddPage('P');
                        $labelsPageCount = 1;
                    } else {
                        $labelsPageCount++;
                    }

                    $labelsCount++;
                }

                // Formato Estandar
            } else {

                $fpdi->AddPage('L');
                foreach ($this->files as $file) {

                    if ($label % 5 === 0) {
                        $fpdi->AddPage('L');
                        $label = 1;
                    }

                    $filename = $file[0];
                    $filepages = $file[1];

                    $count = $fpdi->setSourceFile($filename);

                    //add the pages
                    if ($filepages == 'all') {
                        for ($i = 1; $i <= $count; $i++) {
                            $template = $fpdi->importPage("1", 'CropBox');
                            $size = $fpdi->getTemplateSize($template);

                            $pos = $this->getOriginPosition($label, $size['width'], $size['height']);

                            if ($size['width'] > $size['height']) {
                                $fpdi->useTemplate($template, $pos['x'], $pos['y'], $_w = 146, null, $adjustPageSize = false);
                            } else {
                                $fpdi->useTemplate($template, $pos['x'], $pos['y'], null, $_h = 146, $adjustPageSize = false);
                            }
                        }
                    }
                    $label++;
                }

            }

        } else if ($type == LABEL_TYPE_HALF) {

            $fpdi->AddPage('P');
            foreach ($this->files as $file) {

                if ($label % 3 === 0) {
                    $fpdi->AddPage('P');
                    $label = 1;
                }

                $filename = $file[0];
                $filepages = $file[1];

                $count = $fpdi->setSourceFile($filename);

                //add the pages
                if ($filepages == 'all') {
                    for ($i = 1; $i <= $count; $i++) {

                        $template = $fpdi->importPage("1");

                        if ($label == 2) {
                            $fpdi->useTemplate($template, -145, 140, 200, 140);
                        } else {
                            $fpdi->useTemplate($template, 2, 0, 200, 120);
                        }
                    }
                }
                $label++;
            }
        }

        //output operations
        $mode = $this->switchMode($outputmode);

        if ($mode == 'S') {
            return $fpdi->Output($outputpath, 'S');
        } else {
            if ($fpdi->Output($outputpath, $mode)) {
                return true;
            } else {
                //this gives fatal error..
                //throw new LogicException("Error outputting PDF to '$outputmode'.");
                return false;
            }
        }

    }

    public function mergeTopages($outputmode = 'browser', $outputpath = 'newfile.pdf')
    {
        if (!isset($this->files) || !is_array($this->files)):
            throw new LogicException("No PDFs to merge.");
        endif;

        $fpdi = new $this($this->thermal, $this->format);

        //merger operations
        foreach ($this->files as $file) {
            $filename = $file[0];
            $filepages = $file[1];

            $count = $fpdi->setSourceFile($filename);

            //add the pages
            if ($filepages == 'all') {
                for ($i = 1; $i <= $count; $i++) {
                    $template = $fpdi->importPage($i);
                    $size = $fpdi->getTemplateSize($template);

                    // La orientación depende de que dimensión es mayor
                    if ($size['width'] > $size['height']) {
                        $fpdi->AddPage('L', array($size['width'], $size['height']));
                    } else {
                        $fpdi->AddPage('P', array($size['width'], $size['height']));
                    }

                    $fpdi->useTemplate($template);
                }
            } else {
                foreach ($filepages as $page) {
                    if (!$template = $fpdi->importPage($page)):
                        throw new LogicException(
                            "Could not load page '$page' in PDF '$filename'. Check that the page exists."
                        );
                    endif;
                    $size = $fpdi->getTemplateSize($template);

                    // La orientación depende de que dimensión es mayor
                    if ($size['width'] > $size['height']) {
                        $fpdi->AddPage('L', array($size['width'], $size['height']));
                    } else {
                        $fpdi->AddPage('P', array($size['width'], $size['height']));
                    }
                    $fpdi->useTemplate($template);
                }
            }
        }

        //output operations
        $mode = $this->switchMode($outputmode);

        if ($mode == 'S') {
            return $fpdi->Output($outputpath, 'S');
        } else {
            if ($fpdi->Output($outputpath, $mode)) {
                return true;
            }
        }

    }

    /**
     * Summary of splitPDFPages
     * Split pdf pages into individual pages for each label at the same size
     * @param mixed $path
     * @return string[]
     */
    public function splitPDFPages($path) {

        $fpdi = new $this($this->thermal, $this->format);
        $page_count = $fpdi->setSourceFile($path);

        $base64_pages = [];
    
        for ($page_num = 1; $page_num <= $page_count; $page_num++) {
            // Crear nuevo PDF
            $new_pdf = new $this($this->thermal, $this->format);
            $new_pdf->setSourceFile($path);

            // Importar la página y obtener su tamaño
            $tplIdx = $new_pdf->importPage($page_num);
            $size = $new_pdf->getTemplateSize($tplIdx);

            // Agregar la página con el mismo tamaño
            $new_pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $new_pdf->useTemplate($tplIdx);

            // Guardar la página en un archivo temporal
            $temp_pdf_path = tempnam(sys_get_temp_dir(), 'pdf');
            $new_pdf->Output($temp_pdf_path, 'F');
    
            // Convertir el archivo a Base64
            $pdf_content = file_get_contents($temp_pdf_path);
            $base64_pages[] = base64_encode($pdf_content);
    
            // Eliminar archivo temporal
            unlink($temp_pdf_path);
        }
 
        return $base64_pages;
    }

    /**
     * FPDI uses single characters for specifying the output location.
     * Change our more descriptive string into proper format.
     * @param $mode
     * @return Character
     */
    private function switchMode($mode)
    {
        switch (strtolower($mode)) {
            case 'download':
                return 'D';
                break;
            case 'browser':
                return 'I';
                break;
            case 'file':
                return 'F';
                break;
            case 'string':
                return 'S';
                break;
            default:
                return 'I';
                break;
        }
    }

    /**
     * Takes our provided pages in the form of 1,3,4,16-50 and creates an array of all pages
     * @param $pages
     * @return unknown_type
     */
    private function rewritePages($pages)
    {
        $pages = str_replace(' ', '', $pages);
        $part = explode(',', $pages);

        //parse hyphens
        foreach ($part as $i) {
            $ind = explode('-', $i);

            if (count($ind) == 2) {
                $x = $ind[0]; //start page
                $y = $ind[1]; //end page

                if ($x > $y):
                    throw new LogicException("Starting page, '$x' is greater than ending page '$y'.");
                    return false;
                endif;

                //add middle pages
                while ($x <= $y):
                    $newpages[] = (int) $x;
                    $x++;
                endwhile;
            } else {
                $newpages[] = (int) $ind[0];
            }
        }

        return $newpages;
    }

    /**
     * @param int $label Position de la etiqueta (1,2,3 o 4)
     * @param float|int $width Ancho
     * @param float|int $height Alto
     * @return array Array con las coordenadas de ancho y alto
     */
    public function getOriginPosition($label, $width, $height)
    {

        if (isset($label)) {
            if ($width < $height) { // International shipping label
                if ($label == 1) {
                    $_x = 3;
                    $_y = -40;
                } elseif ($label == 2) {
                    $_x = 149;
                    $_y = -40;
                } elseif ($label == 3) {
                    $_x = 3;
                    $_y = 60;
                } elseif ($label == 4) {
                    $_x = 149;
                    $_y = 60;
                }
            } else { // National Shipping Labels
                if ($label == 1) {
                    $_x = 3;
                    $_y = 5;
                } elseif ($label == 2) {
                    $_x = 150;
                    $_y = 5;
                } elseif ($label == 3) {
                    $_x = 3;
                    $_y = 110;
                } elseif ($label == 4) {
                    $_x = 150;
                    $_y = 110;
                }
            }
        } else {
            throw new LogicException('Origin position nos was indicated');
        }
        return array('x' => $_x, 'y' => $_y);
    }

    /**
     * Se hereda este método de la clase Fpdi
     *
     * Imports a page.
     *
     * @param int $pageNumber The page number.
     * @param string $box The page boundary to import. Default set to PageBoundaries::CROP_BOX.
     * @param bool $groupXObject Define the form XObject as a group XObject to support transparency (if used).
     * @return string A unique string identifying the imported page.
     * @throws CrossReferenceException
     * @throws FilterException
     * @throws PdfParserException
     * @throws PdfTypeException
     * @throws PdfReaderException
     * @see PageBoundaries
     */
    public function importPage($pageNumber, $box = PageBoundaries::CROP_BOX, $groupXObject = true, $importExternalLinks = false)
    {
        if (null === $this->currentReaderId) {
            throw new \BadMethodCallException('No reader initiated. Call setSourceFile() first.');
        }

        $pageId = $this->currentReaderId;

        $pageNumber = (int) $pageNumber;
        $pageId .= '|' . $pageNumber . '|' . ($groupXObject ? '1' : '0');

        // for backwards compatibility with FPDI 1
        $box = \ltrim($box, '/');
        if (!PageBoundaries::isValidName($box)) {
            throw new \InvalidArgumentException(
                \sprintf('Box name is invalid: "%s"', $box)
            );
        }

        $pageId .= '|' . $box;

        if (isset($this->importedPages[$pageId])) {
            return $pageId;
        }

        $reader = $this->getPdfReader($this->currentReaderId);
        $page = $reader->getPage($pageNumber);

        $bbox = $page->getBoundary($box);
        if ($bbox === false) {
            throw new PdfReaderException(
                \sprintf("Page doesn't have a boundary box (%s).", $box),
                PdfReaderException::MISSING_DATA
            );
        }

        $dict = new PdfDictionary();
        $dict->value['Type'] = PdfName::create('XObject');
        $dict->value['Subtype'] = PdfName::create('Form');
        $dict->value['FormType'] = PdfNumeric::create(1);
        $dict->value['BBox'] = $bbox->toPdfArray();

        if ($groupXObject) {
            $this->setMinPdfVersion('1.4');
            $dict->value['Group'] = PdfDictionary::create([
                'Type' => PdfName::create('Group'),
                'S' => PdfName::create('Transparency')
            ]);
        }

        $resources = $page->getAttribute('Resources');
        if ($resources !== null) {
            $dict->value['Resources'] = $resources;
        }

        list($width, $height) = $page->getWidthAndHeight($box);

        $a = 1;
        $b = 0;
        $c = 0;
        $d = 1;
        $e = -$bbox->getLlx();
        $f = -$bbox->getLly();

        $rotation = $page->getRotation();

        // Requisito para etiquetas de tipo Internacional. La internacional es tipo Portaretrato. Necesitamos girarla.
        if ($this->format != LABEL_FORMAT_3A4) {
            if (($width < $height) && !$this->thermal) {
                $rotation = 90;
            }
        }

        if ($rotation !== 0) {
            $rotation *= -1;
            $angle = $rotation * M_PI / 180;
            $a = \cos($angle);
            $b = \sin($angle);
            $c = -$b;
            $d = $a;

            switch ($rotation) {
                case -90:
                    $e = -$bbox->getLly();
                    $f = $bbox->getUrx();
                    break;
                case -180:
                    $e = $bbox->getUrx();
                    $f = $bbox->getUry();
                    break;
                case -270:
                    $e = $bbox->getUry();
                    $f = -$bbox->getLlx();
                    break;
            }
        }

        // we need to rotate/translate
        if ($a != 1 || $b != 0 || $c != 0 || $d != 1 || $e != 0 || $f != 0) {
            $dict->value['Matrix'] = PdfArray::create([
                PdfNumeric::create($a), PdfNumeric::create($b), PdfNumeric::create($c),
                PdfNumeric::create($d), PdfNumeric::create($e), PdfNumeric::create($f)
            ]);
        }

        // try to use the existing content stream
        $pageDict = $page->getPageDictionary();

        try {
            $contentsObject = PdfType::resolve(PdfDictionary::get($pageDict, 'Contents'), $reader->getParser(), true);
            $contents = PdfType::resolve($contentsObject, $reader->getParser());

            // just copy the stream reference if it is only a single stream
            if (
                ($contentsIsStream = ($contents instanceof PdfStream))
                || ($contents instanceof PdfArray && \count($contents->value) === 1)
            ) {
                if ($contentsIsStream) {
                    /**
                     * @var PdfIndirectObject $contentsObject
                     */
                    $stream = $contents;
                } else {
                    $stream = PdfType::resolve($contents->value[0], $reader->getParser());
                }

                $filter = PdfDictionary::get($stream->value, 'Filter');
                if (!$filter instanceof PdfNull) {
                    $dict->value['Filter'] = $filter;
                }
                $length = PdfType::resolve(PdfDictionary::get($stream->value, 'Length'), $reader->getParser());
                $dict->value['Length'] = $length;
                $stream->value = $dict;
                // otherwise extract it from the array and re-compress the whole stream
            } else {
                $streamContent = $this->compress
                ? \gzcompress($page->getContentStream())
                : $page->getContentStream();

                $dict->value['Length'] = PdfNumeric::create(\strlen($streamContent));
                if ($this->compress) {
                    $dict->value['Filter'] = PdfName::create('FlateDecode');
                }

                $stream = PdfStream::create($dict, $streamContent);
            }
            // Catch faulty pages and use an empty content stream
        } catch (FpdiException $e) {
            $dict->value['Length'] = PdfNumeric::create(0);
            $stream = PdfStream::create($dict, '');
        }

        $this->importedPages[$pageId] = [
            'objectNumber' => null,
            'readerId' => $this->currentReaderId,
            'id' => 'TPL' . $this->getNextTemplateId(),
            'width' => $width / $this->k,
            'height' => $height / $this->k,
            'stream' => $stream,
            'externalLinks' => []
        ];

        return $pageId;
    }

    /**
     * Método para crear el pdf de un manifiesto de Utilidades -> Resumen de pedidos
     */
    public function createManifest($filepath, $client_pages)
    {
        $pdf = new PDF_MC_Table();      
        
        foreach ($client_pages as $client_page) {
            $pdf->AddPage(); 
            $pdf->SetFont('helvetica','B',10);

            // CABECERA 
            $pdf->Cell(70,7,' ','LT',0,'L',0);
            $pdf->Cell(120,7,'NOMBRE CLIENTE: '.$client_page['name'],'TR',0,'L',0);
            $pdf->Ln();
            $pdf->SetFont('helvetica','B',14);
            $pdf->Cell(70,8,$client_page['company'],'L',0,'C',0);
            $pdf->SetFont('helvetica','B',10);
            $pdf->Cell(120,8, mb_convert_encoding("CÓDIGO CLIENTE: ", "ISO-8859-1", "UTF-8") . $client_page['client_code'],'R',0,'',0);
            $pdf->Ln();
            $pdf->Cell(70,7,'','LB',0,'L',0);
            $pdf->Cell(120,7,'FECHA: '. date('d/m/Y'),'BR',0,'L',0);
            $pdf->Ln(30);

            // TABLA
            // cabecera tabla
            $pdf->SetFont('helvetica','B',7);
            $pdf->Cell(30,7,mb_convert_encoding("COD ENVÍO", "ISO-8859-1", "UTF-8"),'LTB',0,'L',0);
            $pdf->Cell(40,7,mb_convert_encoding("COD BULTOS", "ISO-8859-1", "UTF-8"),'TB',0,'L',0);
            $pdf->Cell(60,7,mb_convert_encoding("DEST./CONSIG", "ISO-8859-1", "UTF-8"),'TB',0,'L',0);
            $pdf->Cell(15,7,'BULTOS','TB',0,'L',0);
            $pdf->Cell(15,7,'KILOS','TB',0,'L',0);
            $pdf->Cell(15,7,'REEMB.','TB',0,'L',0);
            $pdf->Cell(15,7,'SEGURO','RTB',1,'L',0);
            $pdf->Ln(1);

            $pdf->SetFont('helvetica','',7);

            // contenido de la tabla
            foreach ($client_page['orders'] as $order) {

                // rows con cada pedido seleccionado
                $pdf->SetWidths(array(30, 40, 60, 15, 15, 15, 15));
                $pdf->SetAligns(array('L', 'L', 'L', 'C', 'C', 'C', 'C'));
                $pdf->Row([
                    $order['exp_number'], 
                    $order['shipping_numbers'], 
                    mb_convert_encoding($order['customer_name'] ." \n " . $order['address'] , "ISO-8859-1", "UTF-8"), 
                    $order['bultos'],
                    $order['total_weight'],
                    $order['cash_on_delivery_value'] . iconv('UTF-8', 'windows-1252', '€'),
                    $order['insurance_value'] . iconv('UTF-8', 'windows-1252', '€')
                ]);
                $pdf->Ln(1);
            }

            // totales por productos agrupados
            $pdf->setFillColor(230,230,230);
            foreach ($client_page['total_products'] as $product) {
                $pdf->Cell(130,6, mb_convert_encoding($product['product'], "ISO-8859-1", "UTF-8") ,'LTB',0,'L',1);
                $pdf->Cell(15,6,$product['total_bultos'],'TB',0,'C',1);
                $pdf->Cell(15,6,$product['total_weight'],'TB',0,'C',1);
                $pdf->Cell(15,6,$product['total_cash_on_delivery_value'] . iconv('UTF-8', 'windows-1252', '€'),'TB',0,'C',1);
                $pdf->Cell(15,6,$product['total_insurance'] . iconv('UTF-8', 'windows-1252', '€'),'RTB',1,'C',1);
                $pdf->Ln(1);
            }

            // totales de la página
            $pdf->SetFont('helvetica','B',7);
            $pdf->Cell(130,4, 'Totales' ,'LTB',0,'L',1);
            $pdf->Cell(15,4,$client_page['total_page']['total_bultos_page'],'TB',0,'C',1);
            $pdf->Cell(15,4,$client_page['total_page']['total_weight_page'],'TB',0,'C',1);
            $pdf->Cell(15,4,$client_page['total_page']['total_cash_on_delivery_value_page'] . iconv('UTF-8', 'windows-1252', '€'),'TB',0,'C',1);
            $pdf->Cell(15,4,$client_page['total_page']['total_insurance_page'] . iconv('UTF-8', 'windows-1252', '€'),'RTB',1,'C',1);
        }    

        return $pdf;
    }
}
