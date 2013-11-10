btce
====

BTC-e API Class


Allows for the use of the Private and Public APIs from BTC-e.

Built in Support For:
	Public API:
		Currency Pair Fees
		Currency Pair Tickers
		Currency Pair Trades
		Currency Pair Depths
		
	Private API:
		Trade (Buy/Sell) Orders
		Checking on Past Orders
		API Query Method
			- Auto-recovery from bad noonces
			- Allows any method with any parameters to be called on btc-e
			
			
	Example Usage provided in Example.php
	
	
	How to integrate:
	require_once('btce-api.php');
	$BTCeAPI = new BTCeAPI({APIKEY},{APISECRET}[,Optional:{START_NOONCE}]);
	
	
	Private API Quick Example:
	$BTCeAPI->makeOrder($amount, $pair, $direction, $price);
	
	Public API Quick Example:
	$BTCeAPI->getPairDepth('btc_usd');
