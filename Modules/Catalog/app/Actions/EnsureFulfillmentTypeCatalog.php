<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Modules\Catalog\Models\FulfillmentType;
use Modules\Settings\Models\Language;

final class EnsureFulfillmentTypeCatalog
{
    /** @var list<array{code: string, sort_order: int}> */
    private const TYPES = [
        ['code' => 'merchant', 'sort_order' => 1],
        ['code' => 'dropship', 'sort_order' => 2],
        ['code' => 'third_party_logistics', 'sort_order' => 3],
        ['code' => 'store_pickup', 'sort_order' => 4],
        ['code' => 'local_delivery', 'sort_order' => 5],
        ['code' => 'digital', 'sort_order' => 6],
    ];

    /** @var array<string, array<string, array{name: string, description: string}>> */
    private const TRANSLATIONS = [
        'en' => [
            'merchant' => ['name' => 'Merchant fulfillment', 'description' => 'The merchant stores, packs, and ships the order.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'A supplier ships the order directly to the customer.'],
            'third_party_logistics' => ['name' => 'Third-party logistics', 'description' => 'A third-party logistics provider stores, packs, and ships the order.'],
            'store_pickup' => ['name' => 'Store pickup', 'description' => 'The customer collects the order from a Store location.'],
            'local_delivery' => ['name' => 'Local delivery', 'description' => 'The Store or a local courier delivers within the service area.'],
            'digital' => ['name' => 'Digital delivery', 'description' => 'The customer receives the product electronically without physical shipping.'],
        ],
        'ar' => [
            'merchant' => ['name' => 'تنفيذ التاجر', 'description' => 'يتولى التاجر تخزين الطلب وتجهيزه وشحنه.'],
            'dropship' => ['name' => 'الشحن المباشر', 'description' => 'يشحن المورد الطلب مباشرة إلى العميل.'],
            'third_party_logistics' => ['name' => 'الخدمات اللوجستية لطرف ثالث', 'description' => 'يتولى مزود لوجستي خارجي تخزين الطلب وتجهيزه وشحنه.'],
            'store_pickup' => ['name' => 'الاستلام من المتجر', 'description' => 'يستلم العميل الطلب من أحد مواقع المتجر.'],
            'local_delivery' => ['name' => 'التوصيل المحلي', 'description' => 'يوصل المتجر أو ناقل محلي الطلب داخل منطقة الخدمة.'],
            'digital' => ['name' => 'التسليم الرقمي', 'description' => 'يتلقى العميل المنتج إلكترونياً دون شحن مادي.'],
        ],
        'zh_CN' => [
            'merchant' => ['name' => '商家履约', 'description' => '商家负责存储、包装并配送订单。'],
            'dropship' => ['name' => '代发货', 'description' => '供应商将订单直接发送给客户。'],
            'third_party_logistics' => ['name' => '第三方物流', 'description' => '第三方物流服务商负责存储、包装并配送订单。'],
            'store_pickup' => ['name' => '到店自提', 'description' => '客户从商店地点领取订单。'],
            'local_delivery' => ['name' => '本地配送', 'description' => '商店或本地配送员在服务区域内送达订单。'],
            'digital' => ['name' => '数字交付', 'description' => '客户以电子方式接收产品，无需实体配送。'],
        ],
        'zh_TW' => [
            'merchant' => ['name' => '商家履約', 'description' => '商家負責儲存、包裝並配送訂單。'],
            'dropship' => ['name' => '代發貨', 'description' => '供應商將訂單直接寄送給顧客。'],
            'third_party_logistics' => ['name' => '第三方物流', 'description' => '第三方物流服務商負責儲存、包裝並配送訂單。'],
            'store_pickup' => ['name' => '到店取貨', 'description' => '顧客從商店據點領取訂單。'],
            'local_delivery' => ['name' => '本地配送', 'description' => '商店或本地配送員在服務區域內送達訂單。'],
            'digital' => ['name' => '數位交付', 'description' => '顧客以電子方式接收產品，無需實體配送。'],
        ],
        'cs' => [
            'merchant' => ['name' => 'Plnění obchodníkem', 'description' => 'Obchodník objednávku skladuje, balí a odesílá.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'Dodavatel odesílá objednávku přímo zákazníkovi.'],
            'third_party_logistics' => ['name' => 'Logistika třetí strany', 'description' => 'Externí logistický poskytovatel objednávku skladuje, balí a odesílá.'],
            'store_pickup' => ['name' => 'Vyzvednutí v obchodě', 'description' => 'Zákazník si objednávku vyzvedne na provozovně.'],
            'local_delivery' => ['name' => 'Místní doručení', 'description' => 'Obchod nebo místní kurýr doručí objednávku v obsluhované oblasti.'],
            'digital' => ['name' => 'Digitální doručení', 'description' => 'Zákazník obdrží produkt elektronicky bez fyzické přepravy.'],
        ],
        'da' => [
            'merchant' => ['name' => 'Behandling af forhandler', 'description' => 'Forhandleren opbevarer, pakker og sender ordren.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'En leverandør sender ordren direkte til kunden.'],
            'third_party_logistics' => ['name' => 'Tredjepartslogistik', 'description' => 'En ekstern logistikudbyder opbevarer, pakker og sender ordren.'],
            'store_pickup' => ['name' => 'Afhentning i butik', 'description' => 'Kunden afhenter ordren på en butikslokation.'],
            'local_delivery' => ['name' => 'Lokal levering', 'description' => 'Butikken eller et lokalt bud leverer inden for serviceområdet.'],
            'digital' => ['name' => 'Digital levering', 'description' => 'Kunden modtager produktet elektronisk uden fysisk forsendelse.'],
        ],
        'nl' => [
            'merchant' => ['name' => 'Afhandeling door verkoper', 'description' => 'De verkoper bewaart, verpakt en verzendt de bestelling.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'Een leverancier verzendt de bestelling rechtstreeks naar de klant.'],
            'third_party_logistics' => ['name' => 'Logistiek door derden', 'description' => 'Een externe logistieke dienstverlener bewaart, verpakt en verzendt de bestelling.'],
            'store_pickup' => ['name' => 'Afhalen in de winkel', 'description' => 'De klant haalt de bestelling op bij een winkellocatie.'],
            'local_delivery' => ['name' => 'Lokale bezorging', 'description' => 'De winkel of een lokale koerier bezorgt binnen het servicegebied.'],
            'digital' => ['name' => 'Digitale levering', 'description' => 'De klant ontvangt het product elektronisch zonder fysieke verzending.'],
        ],
        'fi' => [
            'merchant' => ['name' => 'Kauppiaan toimitus', 'description' => 'Kauppias varastoi, pakkaa ja lähettää tilauksen.'],
            'dropship' => ['name' => 'Suoratoimitus', 'description' => 'Toimittaja lähettää tilauksen suoraan asiakkaalle.'],
            'third_party_logistics' => ['name' => 'Kolmannen osapuolen logistiikka', 'description' => 'Ulkoinen logistiikkapalvelu varastoi, pakkaa ja lähettää tilauksen.'],
            'store_pickup' => ['name' => 'Nouto myymälästä', 'description' => 'Asiakas noutaa tilauksen myymälästä.'],
            'local_delivery' => ['name' => 'Paikallinen toimitus', 'description' => 'Myymälä tai paikallinen kuriiri toimittaa palvelualueella.'],
            'digital' => ['name' => 'Digitaalinen toimitus', 'description' => 'Asiakas vastaanottaa tuotteen sähköisesti ilman fyysistä toimitusta.'],
        ],
        'fr' => [
            'merchant' => ['name' => 'Traitement par le marchand', 'description' => 'Le marchand stocke, prépare et expédie la commande.'],
            'dropship' => ['name' => 'Livraison directe', 'description' => 'Un fournisseur expédie la commande directement au client.'],
            'third_party_logistics' => ['name' => 'Logistique tierce', 'description' => 'Un prestataire logistique tiers stocke, prépare et expédie la commande.'],
            'store_pickup' => ['name' => 'Retrait en magasin', 'description' => 'Le client retire la commande dans un point de vente.'],
            'local_delivery' => ['name' => 'Livraison locale', 'description' => 'La boutique ou un coursier local livre dans la zone desservie.'],
            'digital' => ['name' => 'Livraison numérique', 'description' => 'Le client reçoit le produit électroniquement sans expédition physique.'],
        ],
        'de' => [
            'merchant' => ['name' => 'Händler-Fulfillment', 'description' => 'Der Händler lagert, verpackt und versendet die Bestellung.'],
            'dropship' => ['name' => 'Direktversand', 'description' => 'Ein Lieferant versendet die Bestellung direkt an den Kunden.'],
            'third_party_logistics' => ['name' => 'Drittanbieter-Logistik', 'description' => 'Ein externer Logistikdienstleister lagert, verpackt und versendet die Bestellung.'],
            'store_pickup' => ['name' => 'Abholung im Geschäft', 'description' => 'Der Kunde holt die Bestellung an einem Store-Standort ab.'],
            'local_delivery' => ['name' => 'Lokale Lieferung', 'description' => 'Der Store oder ein lokaler Kurier liefert innerhalb des Servicegebiets.'],
            'digital' => ['name' => 'Digitale Bereitstellung', 'description' => 'Der Kunde erhält das Produkt elektronisch ohne physischen Versand.'],
        ],
        'hi' => [
            'merchant' => ['name' => 'व्यापारी पूर्ति', 'description' => 'व्यापारी ऑर्डर को संग्रहीत, पैक और भेजता है।'],
            'dropship' => ['name' => 'ड्रॉपशिपिंग', 'description' => 'आपूर्तिकर्ता ऑर्डर सीधे ग्राहक को भेजता है।'],
            'third_party_logistics' => ['name' => 'तृतीय-पक्ष लॉजिस्टिक्स', 'description' => 'तृतीय-पक्ष लॉजिस्टिक्स प्रदाता ऑर्डर को संग्रहीत, पैक और भेजता है।'],
            'store_pickup' => ['name' => 'स्टोर से पिकअप', 'description' => 'ग्राहक स्टोर स्थान से ऑर्डर प्राप्त करता है।'],
            'local_delivery' => ['name' => 'स्थानीय डिलीवरी', 'description' => 'स्टोर या स्थानीय कूरियर सेवा क्षेत्र में ऑर्डर पहुँचाता है।'],
            'digital' => ['name' => 'डिजिटल डिलीवरी', 'description' => 'ग्राहक को उत्पाद इलेक्ट्रॉनिक रूप से मिलता है, भौतिक शिपिंग नहीं होती।'],
        ],
        'it' => [
            'merchant' => ['name' => 'Evasione del commerciante', 'description' => 'Il commerciante conserva, prepara e spedisce l’ordine.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'Un fornitore spedisce l’ordine direttamente al cliente.'],
            'third_party_logistics' => ['name' => 'Logistica di terze parti', 'description' => 'Un fornitore logistico esterno conserva, prepara e spedisce l’ordine.'],
            'store_pickup' => ['name' => 'Ritiro in negozio', 'description' => 'Il cliente ritira l’ordine presso una sede del negozio.'],
            'local_delivery' => ['name' => 'Consegna locale', 'description' => 'Il negozio o un corriere locale consegna nell’area di servizio.'],
            'digital' => ['name' => 'Consegna digitale', 'description' => 'Il cliente riceve il prodotto elettronicamente senza spedizione fisica.'],
        ],
        'ja' => [
            'merchant' => ['name' => '加盟店による発送', 'description' => '加盟店が注文を保管、梱包、発送します。'],
            'dropship' => ['name' => 'ドロップシッピング', 'description' => '仕入先が注文を顧客へ直接発送します。'],
            'third_party_logistics' => ['name' => 'サードパーティ物流', 'description' => '外部の物流業者が注文を保管、梱包、発送します。'],
            'store_pickup' => ['name' => '店舗受け取り', 'description' => '顧客が店舗で注文を受け取ります。'],
            'local_delivery' => ['name' => '地域配送', 'description' => '店舗または地域の配送業者がサービス地域内に届けます。'],
            'digital' => ['name' => 'デジタル配信', 'description' => '物理的な配送なしで製品を電子的に受け取ります。'],
        ],
        'ko' => [
            'merchant' => ['name' => '판매자 주문 처리', 'description' => '판매자가 주문을 보관, 포장 및 배송합니다.'],
            'dropship' => ['name' => '드롭쉬핑', 'description' => '공급업체가 고객에게 주문을 직접 배송합니다.'],
            'third_party_logistics' => ['name' => '제3자 물류', 'description' => '외부 물류 제공업체가 주문을 보관, 포장 및 배송합니다.'],
            'store_pickup' => ['name' => '매장 픽업', 'description' => '고객이 매장 위치에서 주문을 수령합니다.'],
            'local_delivery' => ['name' => '지역 배송', 'description' => '매장 또는 지역 배송업체가 서비스 지역 내에 배송합니다.'],
            'digital' => ['name' => '디지털 전달', 'description' => '실물 배송 없이 제품을 전자적으로 전달받습니다.'],
        ],
        'nb' => [
            'merchant' => ['name' => 'Oppfyllelse av forhandler', 'description' => 'Forhandleren lagrer, pakker og sender bestillingen.'],
            'dropship' => ['name' => 'Direktelevering', 'description' => 'En leverandør sender bestillingen direkte til kunden.'],
            'third_party_logistics' => ['name' => 'Tredjepartslogistikk', 'description' => 'En ekstern logistikkleverandør lagrer, pakker og sender bestillingen.'],
            'store_pickup' => ['name' => 'Henting i butikk', 'description' => 'Kunden henter bestillingen på et butikksted.'],
            'local_delivery' => ['name' => 'Lokal levering', 'description' => 'Butikken eller et lokalt bud leverer innenfor serviceområdet.'],
            'digital' => ['name' => 'Digital levering', 'description' => 'Kunden mottar produktet elektronisk uten fysisk frakt.'],
        ],
        'fa' => [
            'merchant' => ['name' => 'انجام سفارش توسط فروشنده', 'description' => 'فروشنده سفارش را نگهداری، بسته‌بندی و ارسال می‌کند.'],
            'dropship' => ['name' => 'ارسال مستقیم', 'description' => 'تأمین‌کننده سفارش را مستقیماً برای مشتری ارسال می‌کند.'],
            'third_party_logistics' => ['name' => 'لجستیک شخص ثالث', 'description' => 'ارائه‌دهنده لجستیک خارجی سفارش را نگهداری، بسته‌بندی و ارسال می‌کند.'],
            'store_pickup' => ['name' => 'تحویل از فروشگاه', 'description' => 'مشتری سفارش را از محل فروشگاه دریافت می‌کند.'],
            'local_delivery' => ['name' => 'تحویل محلی', 'description' => 'فروشگاه یا پیک محلی سفارش را در محدوده خدمات تحویل می‌دهد.'],
            'digital' => ['name' => 'تحویل دیجیتال', 'description' => 'مشتری محصول را بدون ارسال فیزیکی به‌صورت الکترونیکی دریافت می‌کند.'],
        ],
        'pl' => [
            'merchant' => ['name' => 'Realizacja przez sprzedawcę', 'description' => 'Sprzedawca przechowuje, pakuje i wysyła zamówienie.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'Dostawca wysyła zamówienie bezpośrednio do klienta.'],
            'third_party_logistics' => ['name' => 'Logistyka zewnętrzna', 'description' => 'Zewnętrzny operator logistyczny przechowuje, pakuje i wysyła zamówienie.'],
            'store_pickup' => ['name' => 'Odbiór w sklepie', 'description' => 'Klient odbiera zamówienie w lokalizacji sklepu.'],
            'local_delivery' => ['name' => 'Dostawa lokalna', 'description' => 'Sklep lub lokalny kurier dostarcza zamówienie w obszarze obsługi.'],
            'digital' => ['name' => 'Dostawa cyfrowa', 'description' => 'Klient otrzymuje produkt elektronicznie bez fizycznej wysyłki.'],
        ],
        'pt_BR' => [
            'merchant' => ['name' => 'Processamento pelo lojista', 'description' => 'O lojista armazena, embala e envia o pedido.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'Um fornecedor envia o pedido diretamente ao cliente.'],
            'third_party_logistics' => ['name' => 'Logística terceirizada', 'description' => 'Um operador logístico externo armazena, embala e envia o pedido.'],
            'store_pickup' => ['name' => 'Retirada na loja', 'description' => 'O cliente retira o pedido em uma unidade da loja.'],
            'local_delivery' => ['name' => 'Entrega local', 'description' => 'A loja ou um entregador local faz a entrega na área de atendimento.'],
            'digital' => ['name' => 'Entrega digital', 'description' => 'O cliente recebe o produto eletronicamente, sem envio físico.'],
        ],
        'pt_PT' => [
            'merchant' => ['name' => 'Processamento pelo comerciante', 'description' => 'O comerciante armazena, embala e envia a encomenda.'],
            'dropship' => ['name' => 'Dropshipping', 'description' => 'Um fornecedor envia a encomenda diretamente ao cliente.'],
            'third_party_logistics' => ['name' => 'Logística de terceiros', 'description' => 'Um operador logístico externo armazena, embala e envia a encomenda.'],
            'store_pickup' => ['name' => 'Levantamento na loja', 'description' => 'O cliente levanta a encomenda numa localização da loja.'],
            'local_delivery' => ['name' => 'Entrega local', 'description' => 'A loja ou um estafeta local entrega na área de serviço.'],
            'digital' => ['name' => 'Entrega digital', 'description' => 'O cliente recebe o produto eletronicamente, sem envio físico.'],
        ],
        'es' => [
            'merchant' => ['name' => 'Preparación por el comerciante', 'description' => 'El comerciante almacena, prepara y envía el pedido.'],
            'dropship' => ['name' => 'Envío directo', 'description' => 'Un proveedor envía el pedido directamente al cliente.'],
            'third_party_logistics' => ['name' => 'Logística de terceros', 'description' => 'Un proveedor logístico externo almacena, prepara y envía el pedido.'],
            'store_pickup' => ['name' => 'Recogida en tienda', 'description' => 'El cliente recoge el pedido en una ubicación de la tienda.'],
            'local_delivery' => ['name' => 'Entrega local', 'description' => 'La tienda o un mensajero local entrega dentro del área de servicio.'],
            'digital' => ['name' => 'Entrega digital', 'description' => 'El cliente recibe el producto electrónicamente sin envío físico.'],
        ],
        'sv' => [
            'merchant' => ['name' => 'Hantering av handlare', 'description' => 'Handlaren lagrar, packar och skickar beställningen.'],
            'dropship' => ['name' => 'Direktleverans', 'description' => 'En leverantör skickar beställningen direkt till kunden.'],
            'third_party_logistics' => ['name' => 'Tredjepartslogistik', 'description' => 'En extern logistikleverantör lagrar, packar och skickar beställningen.'],
            'store_pickup' => ['name' => 'Hämtning i butik', 'description' => 'Kunden hämtar beställningen på en butiksplats.'],
            'local_delivery' => ['name' => 'Lokal leverans', 'description' => 'Butiken eller ett lokalt bud levererar inom serviceområdet.'],
            'digital' => ['name' => 'Digital leverans', 'description' => 'Kunden tar emot produkten elektroniskt utan fysisk frakt.'],
        ],
        'th' => [
            'merchant' => ['name' => 'ร้านค้าจัดการคำสั่งซื้อ', 'description' => 'ร้านค้าจัดเก็บ บรรจุ และจัดส่งคำสั่งซื้อ'],
            'dropship' => ['name' => 'ดรอปชิป', 'description' => 'ซัพพลายเออร์จัดส่งคำสั่งซื้อให้ลูกค้าโดยตรง'],
            'third_party_logistics' => ['name' => 'โลจิสติกส์บุคคลที่สาม', 'description' => 'ผู้ให้บริการโลจิสติกส์ภายนอกจัดเก็บ บรรจุ และจัดส่งคำสั่งซื้อ'],
            'store_pickup' => ['name' => 'รับสินค้าที่ร้าน', 'description' => 'ลูกค้ารับคำสั่งซื้อจากสถานที่ตั้งของร้านค้า'],
            'local_delivery' => ['name' => 'จัดส่งในพื้นที่', 'description' => 'ร้านค้าหรือผู้ส่งสินค้าในพื้นที่จัดส่งภายในเขตบริการ'],
            'digital' => ['name' => 'จัดส่งแบบดิจิทัล', 'description' => 'ลูกค้าได้รับผลิตภัณฑ์ทางอิเล็กทรอนิกส์โดยไม่มีการจัดส่งทางกายภาพ'],
        ],
        'tr' => [
            'merchant' => ['name' => 'Satıcı tarafından karşılama', 'description' => 'Satıcı siparişi depolar, paketler ve gönderir.'],
            'dropship' => ['name' => 'Stoksuz satış', 'description' => 'Tedarikçi siparişi doğrudan müşteriye gönderir.'],
            'third_party_logistics' => ['name' => 'Üçüncü taraf lojistik', 'description' => 'Harici lojistik sağlayıcısı siparişi depolar, paketler ve gönderir.'],
            'store_pickup' => ['name' => 'Mağazadan teslim alma', 'description' => 'Müşteri siparişi bir mağaza konumundan teslim alır.'],
            'local_delivery' => ['name' => 'Yerel teslimat', 'description' => 'Mağaza veya yerel kurye hizmet alanı içinde teslimat yapar.'],
            'digital' => ['name' => 'Dijital teslimat', 'description' => 'Müşteri ürünü fiziksel gönderim olmadan elektronik olarak alır.'],
        ],
        'ur' => [
            'merchant' => ['name' => 'تاجر کی جانب سے تکمیل', 'description' => 'تاجر آرڈر کو محفوظ، پیک اور روانہ کرتا ہے۔'],
            'dropship' => ['name' => 'ڈراپ شپنگ', 'description' => 'سپلائر آرڈر براہ راست صارف کو بھیجتا ہے۔'],
            'third_party_logistics' => ['name' => 'تیسرے فریق کی لاجسٹکس', 'description' => 'بیرونی لاجسٹکس فراہم کنندہ آرڈر کو محفوظ، پیک اور روانہ کرتا ہے۔'],
            'store_pickup' => ['name' => 'اسٹور سے وصولی', 'description' => 'صارف اسٹور کے مقام سے آرڈر وصول کرتا ہے۔'],
            'local_delivery' => ['name' => 'مقامی ترسیل', 'description' => 'اسٹور یا مقامی کوریئر سروس کے علاقے میں آرڈر پہنچاتا ہے۔'],
            'digital' => ['name' => 'ڈیجیٹل ترسیل', 'description' => 'صارف کو مصنوعہ بغیر جسمانی شپنگ کے الیکٹرانک طور پر ملتی ہے۔'],
        ],
    ];

    public function ensure(): void
    {
        $languages = Language::query()->orderBy('locale')->get(['locale']);

        foreach (self::TYPES as $attributes) {
            $fulfillmentType = FulfillmentType::query()->updateOrCreate(
                ['code' => $attributes['code']],
                [...$attributes, 'is_active' => true],
            );

            foreach ($languages as $language) {
                $locale = (string) $language->locale;
                $translation = self::TRANSLATIONS[$locale][$attributes['code']]
                    ?? self::TRANSLATIONS['en'][$attributes['code']];

                $fulfillmentType->translations()->updateOrCreate(
                    ['locale' => $locale],
                    $translation,
                );
            }
        }
    }
}
