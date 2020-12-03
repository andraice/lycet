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
use Greenter\Model\Sale\Invoice;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
  private $client;
  private $CURRENCY;

  public function __construct(HttpClientInterface $client)
  {
    $this->client = $client;
    $this->CURRENCY = [
      1 => 'PEN',        # Soles
      2 => 'USD',        # Dollars
      3 => 'EUR',        # Euros
    ];
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

    $json_request = json_decode($request->getContent());

    switch ($json_request->operacion) {
      case 'generar_anulacion':

        $dmas_baja_json = json_decode('{
            "correlativo": "1",
            "fecGeneracion": "2010-02-21T08:36:21-05:00",
            "fecComunicacion": "2020-02-20T23:44:35-05:00",
            "company": {
              "ruc": "20522718786",
              "razonSocial": "PLACA MASS E.I.R.L.",
              "nombreComercial": "PLACA MASS",
              "address": {
                "ubigueo": "150101",
                "codigoPais": "PE",
                "departamento": "LIMA",
                "provincia": "LIMA",
                "distrito": "SAN MARTIN DE PORRES",
                "urbanizacion": "-",
                "direccion": "Cal. 8 Mza. I Lote. 10 Apv Resid Monte Azul"
              }
            },
            "details": [
              {
                "tipoDoc": "",
                "serie": "",
                "correlativo": "",
                "desMotivoBaja": ""
              }
            ]
          }');

        break;

      case '':
        $dmask_json = json_decode('{
        "unidad": "",
        "cantidad": 0.0,
        "codProducto": "SS",
        "descripcion": "",
        "mtoValorUnitario": 0.0,
        "mtoBaseIgv": 0.0,
        "porcentajeIgv": 0.0,
        "igv": 0.0,
        "tipAfeIgv": "10",
        "totalImpuestos": 0.0,
        "mtoPrecioUnitario": 0.0,
        "mtoValorVenta": 0.0
        }', true);
          
        $mask_json = json_decode('{
          "ublVersion": "2.1",
          "tipoOperacion": "0101",
          "tipoDoc": "",
          "serie": "",
          "correlativo": "",
          "fechaEmision": "",
          "client": {
            "tipoDoc": "",
            "numDoc": "",
            "rznSocial": "",
            "address": {
              "codigoPais": "PE",
              "departamento": "LIMA",
              "provincia": "LIMA",
              "distrito": "-",
              "urbanizacion": "-",
              "direccion": ""
            }
          },
          "company": {
            "ruc": "20522718786",
            "razonSocial": "PLACA MASS E.I.R.L.",
            "nombreComercial": "PLACA MASS",
            "address": {
              "ubigueo": "150101",
              "codigoPais": "PE",
              "departamento": "LIMA",
              "provincia": "LIMA",
              "distrito": "SAN MARTIN DE PORRES",
              "urbanizacion": "-",
              "direccion": "Cal. 8 Mza. I Lote. 10 Apv Resid Monte Azul"
            }
          },
          "tipoMoneda": "PEN",
          "mtoOperGravadas": 0.0,
          "mtoIGV": 0,
          "totalImpuestos": 0.0,
          "valorVenta": 0.0,
          "subTotal": 0.0,
          "mtoImpVenta": 0.0,
          "details": [],
          "legends": [
            {
              "code": "1000",
              "value": ""
            }
          ]
        }', true);


        // convertir odoo -> lycet
        $mask_json['tipoOperacion'] = '01' . str_pad($json_request->sunat_transaction, 2, "0", STR_PAD_LEFT);
        $mask_json['serie'] = $json_request->serie;
        $mask_json['correlativo'] = str_pad($json_request->numero, 8, "0", STR_PAD_LEFT);
        $mask_json['fechaEmision'] = date("Y-m-d\T12:34:00+01:00", strtotime($json_request->fecha_de_emision));

        $mask_json['tipoDoc'] = str_pad($json_request->tipo_de_comprobante, 2, "0", STR_PAD_LEFT);
        $mask_json['compra'] = $json_request->orden_compra_servicio;
        $mask_json['observacion'] = $json_request->observaciones;

        if ($mask_json['observacion'] != "" and $json_request->numero_guia != "") {
          $mask_json['observacion'] = $mask_json['observacion'] . " | Guias Remisión: " . $json_request->numero_guia;
        } else if ($json_request->numero_guia != "") {
          $mask_json['observacion'] = "Guias Remisión: " . $json_request->numero_guia;
        }

        $mask_json['client']['rznSocial'] = $json_request->cliente_denominacion;
        $mask_json['client']['numDoc'] = $json_request->cliente_numero_de_documento;
        $mask_json['client']['tipoDoc'] = $json_request->cliente_tipo_de_documento;

        $mask_json['tipoMoneda'] = $this->CURRENCY[$json_request->moneda];

        $mask_json['mtoOperGravadas'] = $json_request->total_gravada;
        $mask_json['mtoOperInafectas'] = $json_request->total_inafecta;
        $mask_json['mtoOperExoneradas'] = $json_request->total_exonerada;
        //$mask_json['mtoOperGratuitas'] = $json_request->total_gratuita;
        //$mask_json['mtoIGVGratuitas'] = 0.0;

        $mask_json['mtoIGV'] = $json_request->total_igv;
        $mask_json['totalImpuestos'] = $json_request->total_igv;
        $mask_json['valorVenta'] = $json_request->total_gravada;
        $mask_json['subTotal'] = $json_request->total;
        $mask_json['mtoImpVenta'] = $json_request->total;

        foreach ($json_request->items as $item) {
          $dmask_json['unidad'] = $item->unidad_de_medida;
          $dmask_json['cantidad'] = $item->cantidad;
          $dmask_json['codProducto'] = $item->codigo;
          $dmask_json['descripcion'] = $item->descripcion;
          $dmask_json['mtoValorUnitario'] = round($item->valor_unitario, 4);
          $dmask_json['mtoPrecioUnitario'] = round($item->precio_unitario, 4);
          $dmask_json['mtoBaseIgv'] = round($item->subtotal, 2);
          $dmask_json['mtoValorVenta'] = round($item->subtotal, 4);
          $dmask_json['porcentajeIgv'] = round($json_request->porcentaje_de_igv, 2);
          $dmask_json['igv'] = round($item->igv, 2);
          $dmask_json['tipAfeIgv'] = $item->tipo_de_igv;
          $dmask_json['totalImpuestos'] = round($item->igv, 4);
          if ((float)$item->descuento > 0.0) {
            $dmask_json['descuento'][] = array(
              "codTipo" => "00",
              "montoBase" => $item->cantidad * round($item->valor_unitario, 4),
              "factor" => $item->descuento_porcentaje,
              "monto" => $item->descuento,
            );
          }
          $mask_json['details'][] = $dmask_json;
        }

        $formatter = new NumeroALetras();
        $mask_json['legends'][0]['value'] = $formatter->toInvoice($json_request->total, 2, strtoupper($json_request->moneda_texto));

        // fin 
        $data_json = json_encode($mask_json);

        //var_dump($mask_json);

        $response = $this->client->request(
          'POST',
          'http://localhost:8000/api/v1/invoice/send?token=123456',
          ['body' => $data_json],
          ['headers' => [
            'Content-Type' => 'application/json',
          ]]
        );

        $json_response = json_decode($response->getContent());
        $json_request = json_decode($data_json);


        if ($json_response->sunatResponse->success) {
          file_put_contents('./cdr/' . $json_request->company->ruc . '-' . $json_request->tipoDoc . '-' . $json_request->serie . '-' . $json_request->correlativo . '.zip', base64_decode($json_response->sunatResponse->cdrZip));

          $response_pdf = $this->client->request(
            'POST',
            'http://localhost:8000/api/v1/invoice/pdf?token=123456',
            ['body' => $data_json],
            ['headers' => [
              'Content-Type' => 'application/json',
            ]]
          );

          $enlace_pdf = 'http://142.93.206.123:8000/pdf/' . $json_request->company->ruc . '-' . $json_request->tipoDoc . '-' . $json_request->serie . '-' . $json_request->correlativo . '.pdf';
          $enlace_cdr = 'http://142.93.206.123:8000/cdr/' . $json_request->company->ruc . '-' . $json_request->tipoDoc . '-' . $json_request->serie . '-' . $json_request->correlativo . '.zip';
        }

        break;
    }
    //print_r($json_response);

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
