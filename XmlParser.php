<?php

namespace App\Jobs;

use App\Mail\LimitsEmail;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Settings;
use App\Services\LimitService;
use App\Services\ParserService;
use App\Services\ParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Mail;

class XmlParser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userProduct;

    /**
     * Create a new job instance.
     *
     * XmlParser constructor.
     * @param $userProduct
     */
    public function __construct($userProduct)
    {
        $this->userProduct = $userProduct;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $xml = ParsingService::loader($this->userProduct);

        if($xml){
            $oldItems = ProductItem::where('product_id', $this->userProduct->product_id)->get();
            $result = ParsingService::itemStore($this->userProduct->company_id, $this->userProduct, $xml);

            $productData = [
                'status'        => $result['status'],
                'code'          => $result['code'],
                'type'          => 'Google Product',
                'wait'          => 0,
                'items'         => $result['items'],
                'updated_at'    => Carbon::now()
            ];
            $this->userProduct->update($productData);

            $product = Product::where('id', $this->userProduct->product_id)->first();
            $product->update([
                'title'         => $xml['body']->channel->title,
                'link'          => $xml['body']->channel->link,
                'description'   => $xml['body']->channel->description,
                'updated_at'    => Carbon::now()
            ]);

            $x = 0;
            while($x < $oldItems->count()) {
                $oldItems[$x]->delete();
                $x++;
            }

            //check company limits & mailing
            $result = LimitService::checkXmlLimits($this->userProduct->company_id);
            if($result['errors']){
                ParsingService::notification($this->userProduct->company_id, 'Too much products. Update subscription or delete extra items.', $this->userProduct->id);
                ParsingService::mailing($this->userProduct->company_id);
            }

        } else {
            $productData = [
                'status'        => 'Productfeed not responding',
                'code'          => 400,
                'type'          => 'xml file is invalid or unavailable',
                'wait'          => 0,
                'updated_at'    => Carbon::now()
            ];
            $this->userProduct->update($productData);

            ParsingService::notification($this->userProduct->company_id, 'Productfeed not responding', $this->userProduct->id);
        }
    }

    /**
     * Send error notification
     *
     * @param Exception $exception
     */
    public function failed(Exception $exception)
    {
        $productData = [
            'status'        => 'Productfeed not responding (failed job)',
            'code'          => 400,
            'type'          => 'xml file is invalid or unavailable',
            'wait'          => 0,
            'updated_at'    => Carbon::now()
        ];
        $this->userProduct->update($productData);

        ParsingService::notification($this->userProduct->company_id, 'Productfeed not responding', $this->userProduct->id);
    }
}
