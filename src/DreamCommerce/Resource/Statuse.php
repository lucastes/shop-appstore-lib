<?PHP
namespace DreamCommerce\Resource;

use DreamCommerce\Resource;
use DreamCommerce\Client;

class Statuse extends Resource{
    
    public function __construct(Client $client){
        return parent::__construct($client, 'statuses');
    }
    
}