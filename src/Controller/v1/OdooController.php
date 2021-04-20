<?php

/**
 * Created by PhpStorm.
 * User: Administrador
 * Date: 23/01/2018
 * Time: 02:06 PM.
 */

namespace App\Controller\v1;

use Luecano\NumeroALetras\NumeroALetras;
use App\Service\DocumentRequestInterface;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class OdooController.
 *
 * @Route("/api/v1/odoo")
 */
class OdooController extends AbstractController
{
  private $urlBase;
  private $publicUrlBase;
  private $client;
  private $CURRENCY;
  private $DOCUMENT_TYPE;
  private $TIPO_OPERACION;
  private $TIPO_IGV;
  private $TIPO_NOTACREDITO;

  private $_token = '123456';
  private $_urlModel;

  private $jmsSerializer;
  private $cliente;
  private $empresa;
  /**
   * @var DocumentInterface
   */
  private $document;

  public function __construct(
    SerializerInterface $jmsSerializer,
    HttpClientInterface $client
  ) {
    $this->jmsSerializer = $jmsSerializer;
    $this->urlBase = "http://localhost/lycet/public/api/v1";
    $this->publicUrlBase = "http://142.93.206.123:8000";
    $this->client = $client;
    $this->CURRENCY = [
      1 => 'PEN',        # Soles
      2 => 'USD',        # Dollars
      3 => 'EUR',        # Euros
    ];
    $this->DOCUMENT_TYPE = [
      1 => '01',
      2 => '03',
      3 => '07',
      4 => '08',
    ];

    $this->TIPO_OPERACION = [
      1 => '0101', //'INTERNAL SALE',
      2 => '0102', //'EXPORTATION',
      3 => '0103', //'NON-DOMICILED',
      4 => '0104', //'INTERNAL SALE - ADVANCES',
      5 => '0105', //'ITINERANT SALE',
      6 => '0106', //'GUIDE INVOICE',
      7 => '0107', //'SALE PILADO RICE',
      8 => '0108', //'INVOICE - PROOF OF PERCEPTION',
      10 => '0110', //'INVOICE - SENDING GUIDE',
      11 => '0111', //'INVOICE - CARRIER GUIDE',
      12 => '', //'SALES TICKET - PROOF OF PERCEPTION',
      13 => '0112', //'NATURAL PERSON DEDUCTIBLE EXPENSE',
    ];

    $this->TIPO_IGV = [
      1 => "10",
      2 => "11",
      3 => "12",
      4 => "13",
      5 => "14",
      6 => "15",
      7 => "16",
      8 => "20",
      9 => "30",
      10 => "31",
      11 => "32",
      12 => "33",
      13 => "34",
      14 => "35",
      15 => "36",
      16 => "40",
    ];

    $this->TIPO_NOTACREDITO = [
      1 => "Anulación de la operación",
      2 => "Anulación por error en el RUC",
      3 => "Corrección por error en la descripción",
      4 => "Descuento global",
      5 => "Descuento por ítem",
      6 => "Devolución total",
      7 => "Devolución por ítem",
      8 => "Bonificación",
      9 => "Disminución en el valor",
    ];
  }

  public function getDocument($type)
  {
    switch ($this->DOCUMENT_TYPE[$type]) {
      case '01':
        $d = new Invoice();
        $d->setUblVersion("2.1");
        $d->setTipoDoc('01');
        break;
      case '03':
        $d = new Invoice();
        $d->setUblVersion("2.1");
        $d->setTipoDoc('03');
        break;
      case '07':
        $d = new Note();
        $d->setUblVersion("2.1");
        $d->setTipoDoc('07');
        break;
      case '08':
        $d = new Note();
        $d->setUblVersion("2.1");
        $d->setTipoDoc('08');
        break;
      default:
        $d = null;
        break;
    }
    return $d;
  }

  /**
   * @Route("/send", methods={"POST"})
   *
   * @return Response
   */
  public function send(Request $request): Response
  {
    $enlace_pdf = "";
    $enlace_cdr = "";
    $tz = new \DateTimeZone('America/Lima');
    $json_request = json_decode($request->getContent());

    $direccion = new Address(); // EMPRESA
    $direccion
      ->setUbigueo("150101")
      ->setDepartamento("LIMA")
      ->setProvincia("LIMA")
      ->setDistrito("SAN MARTIN DE PORRES")
      ->setDireccion("Cal. 8 Mza. I Lote. 10 Apv Resid Monte Azul");

    $this->empresa = new Company();
    $this->empresa->setRuc('20522718786')
      ->setRazonSocial("PLACA MASS E.I.R.L.")
      ->setNombreComercial("PLACA MASS")
      ->setAddress($direccion);

    $direccion = new Address(); // CLIENTE
    $direccion->setDireccion($json_request->cliente_direccion)->setCodLocal(null);

    $this->cliente = new Client();
    $this->cliente->setTipoDoc($json_request->cliente_tipo_de_documento)
      ->setNumDoc($json_request->cliente_numero_de_documento)
      ->setRznSocial($json_request->cliente_denominacion)
      ->setAddress($direccion);

    $formatter = new NumeroALetras();
    $legend = new Legend();
    $legend->setCode("1000")
      ->setValue($formatter->toInvoice($json_request->total, 2, strtoupper($json_request->moneda_texto)));

    $this->document = $this->getDocument($json_request->tipo_de_comprobante);
    $this->document
      ->setSerie($json_request->serie)
      ->setCorrelativo($json_request->numero)
      ->setFechaEmision(new \DateTime($json_request->fecha_de_emision, $tz))
      ->setClient($this->cliente)
      ->setCompany($this->empresa)
      ->setTipoMoneda($this->CURRENCY[$json_request->moneda])
      ->setCompra(!empty($json_request->orden_compra_servicio) ? $json_request->orden_compra_servicio : null)
      ->setLegends([$legend])

      ->setMtoOperGravadas($json_request->total_gravada)
      ->setMtoOperInafectas($json_request->total_inafecta)
      ->setMtoOperExoneradas($json_request->total_exonerada)
      ->setMtoOperGratuitas($json_request->total_gratuita)
      ->setMtoIGVGratuitas(!empty($json_request->total_gratuita) ? floatval($json_request->total_gratuita) / 1.18 : null)
      ->setMtoIGV($json_request->total_igv)

      ->setTotalImpuestos($json_request->total_igv)
      ->setMtoImpVenta($json_request->total);

    //FACTURAS Y BOLETAS
    if ($this->document instanceof Invoice) {
      $this->_urlModel = 'invoice';
      $this->document->setTipoOperacion($this->TIPO_OPERACION[$json_request->sunat_transaction])
        /*TODO: Modificar segun odoo*/
        ->setFormaPago(new FormaPagoContado())
        ->setValorVenta($json_request->total_gravada)
        ->setSubTotal($json_request->total);

      if ($json_request->observaciones != "" && $json_request->numero_guia != "") {
        $this->document->setObservacion($json_request->observaciones . " | Guias Remisión: " . $json_request->numero_guia);
      } else if ($json_request->numero_guia != "") {
        $this->document->setObservacion("Guias Remisión: " . $json_request->numero_guia);
      }
    } elseif ($this->document instanceof Note) {
      $this->_urlModel = 'note';
      // NOTA CREDITO
      if ($this->document->getTipoDoc() === "07") {
        $this->document
          ->setTipDocAfectado($this->DOCUMENT_TYPE[$json_request->documento_que_se_modifica_tipo])
          ->setNumDocfectado(sprintf(
            "%s-%s",
            $json_request->documento_que_se_modifica_serie,
            $json_request->documento_que_se_modifica_numero
          ))
          ->setCodMotivo(str_pad($json_request->tipo_de_nota_de_credito, 2, "0", STR_PAD_LEFT))
          ->setDesMotivo($this->TIPO_NOTACREDITO[$json_request->tipo_de_nota_de_credito]);
      }

      // NOTA DEBITO
      if ($this->document->getTipoDoc() === "08") {
        die();
      }
    }




    $detalles = [];
    foreach ($json_request->items as $item) {
      $detalle = new SaleDetail();
      $descuento = null;
      if ((float)$item->descuento > 0.0) {
        $descuento = new Charge();
        $descuento->setCodTipo("00")
          ->setMontoBase($item->cantidad * round($item->valor_unitario, 4))
          ->setFactor($item->descuento_porcentaje)
          ->setMonto($item->descuento);
      }

      $detalle->setUnidad($item->unidad_de_medida)
        ->setCantidad($item->cantidad)
        ->setCodProducto($item->codigo)
        ->setCodProdSunat($item->codigo_producto_sunat)
        ->setDescripcion($item->descripcion)
        ->setMtoValorUnitario(round($item->valor_unitario, 4))
        ->setMtoValorGratuito(0)
        ->setMtoPrecioUnitario(round($item->precio_unitario, 4))
        ->setMtoBaseIgv(round($item->subtotal, 2))
        ->setMtoValorVenta(round($item->subtotal, 4))
        ->setPorcentajeIgv(round($json_request->porcentaje_de_igv, 2))
        ->setIgv(round($item->igv, 2))
        ->setIcbper(round($item->impuesto_bolsas, 2))
        ->setTipAfeIgv($this->TIPO_IGV[$item->tipo_de_igv])
        ->setTotalImpuestos(round($item->igv, 4))
        ->setDescuentos(!is_null($descuento) ? [$descuento] : null);

      array_push($detalles, $detalle);
    }

    $this->document->setDetails($detalles);

    $json = $this->jmsSerializer->serialize($this->document, 'json');
   // return new JsonResponse($json, 200,  [], true);

    $response = $this->client->request(
      'POST',
      $this->urlBase . '/' . $this->_urlModel . '/send?token=' . $this->_token,
      ['body' => $json],
      ['headers' => [
        'Content-Type' => 'application/json',
      ]]
    );

    $json_response = json_decode($response->getContent());
    $json_request = json_decode($json);

    $enlace_pdf = "";
    $enlace_cdr = "";

    if ($json_response->sunatResponse->success) {
      file_put_contents('./cdr/' . $json_request->company->ruc . '-' . $json_request->tipoDoc . '-' . $json_request->serie . '-' . $json_request->correlativo . '.zip', base64_decode($json_response->sunatResponse->cdrZip));

      $response_pdf = $this->client->request(
        'POST',
        $this->urlBase . '/' . $this->_urlModel . '/pdf?token=' . $this->_token,
        ['body' => $json],
        ['headers' => [
          'Content-Type' => 'application/json',
        ]]
      );

      $enlace_pdf = $this->publicUrlBase . "/pdf/" . $json_request->company->ruc . '-' . $json_request->tipoDoc . '-' . $json_request->serie . '-' . $json_request->correlativo . '.pdf';
      $enlace_cdr = $this->publicUrlBase . "/cdr/" . $json_request->company->ruc . '-' . $json_request->tipoDoc . '-' . $json_request->serie . '-' . $json_request->correlativo . '.zip';
    }

    $odoo_response = [
      'enlace' => $enlace_pdf,
      'enlace_del_cdr' => $enlace_cdr,
      'enlace_del_pdf' => $enlace_pdf,
      'enlace_del_xml' => $enlace_cdr,
      'operacion' => 'generar_documento',
      'errors' => '',
      'aceptada_por_sunat' => $json_response->sunatResponse->success ? true : false,
      'sunat_description' => '',
      'sunat_note' => '',
      'sunat_responsecode' => '',
      'sunat_soap_error' => '',
    ];

    if ($json_response->sunatResponse->success) {
      $odoo_response['sunat_descripcion'] = $json_response->sunatResponse->cdrResponse->description;
      $odoo_response['sunat_responsecode'] = $json_response->sunatResponse->cdrResponse->code;
      $odoo_response['sunat_note'] = @$json_response->sunatResponse->cdrResponse->notes[0];
    } else if ($json_response->sunatResponse->error) {
      $odoo_response['errors'] = $json_response->sunatResponse->error->message;
      $odoo_response['sunat_responsecode'] = $json_response->sunatResponse->error->code;
      $odoo_response['sunat_soap_error'] = $json_response->sunatResponse->error->message;
    }

    $response = new Response();
    $response->setContent(json_encode($odoo_response));
    $response->headers->set('Content-Type', 'application/json');

    return $response;
  }
}
