<?php


use App\Http\Controllers\ImportController;
use App\Models\OldCategory;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Common\Entities\Receipt;
use Modules\Shop\Entities\Category;
use Modules\Shop\Entities\Invoice;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\PaymentMethod;
use Modules\Shop\Entities\Product;
use Modules\Shop\Entities\ProductVariant;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $nextTaxNumber = Order::whereNotNull('tax_number')->max('tax_number') + 1;
    return $nextTaxNumber;
//    $products = Product::where('id','<=',9848)->get();
//    foreach ($products as $product) {
//        $product->delete();
//    }
//    dd('saflasssssssddddddddddddddddddddddssssf');
//    $models = \Modules\Shop\Entities\Product::all();
//    foreach ($models as $model){
//        $model->save();
//    }



//    $url = 'https://image.shutterstock.com/image-vector/sample-stamp-grunge-texture-vector-260nw-1389188336.jpg';
//    $model
//        ->addMediaFromUrl($url)
//        ->toMediaCollection('images', 's3');
//    $model->addMediaFromDisk('temp/3gTUiyMCEgp4S0gQK6cCADYaxyD1IbtvQKAfclot.png')
//         ->toMediaCollection('products');
//
//    $model->save();
//    $oldProducts = Product::where('id','=',6827)->paginate(1000);
//    foreach ($oldProducts as $product){
//
//        $media = [];
//
//        $test = str_replace('[', '', $product->gallery);
//        $test2 = str_replace(']', '', $test);
//        foreach (explode(',', $test2) as $key=>$item){
//            $media[$key] = [
//                'id' => $item, 'key' => "temp/".str_replace('"', '', $item), 'new' => true,'url' =>"/storage/temp/".str_replace('"', '', $item)
//            ];
//
//        }
//        $x['media'] = $media;
//
//        $data = $this->validate();
//        $this->repository->update($product->id, $data);
//    }
//
//    $test = '6062,6114,652,6990,273,6977,6883,5856,6430,5761,6906,5843,350,6108,6002,3407,4756,6375,176,576,4741,6879,6373,292,6517,2784,6354,663,6983,6945,7090,7032,6563,6259,6431,556,638,7130,6625,7159,3641,173,7054,6544,2855,6035,161,7020,3202,6905,6849,6660,113,1742,6528,2983,632,3097,658,7174,6939,2990,440,3150,6030,5927,6999,6127,3071,3140,160,644,3700,3093,6946,5808,5998,6922,6861,5769,357,6257,6141,6178,6010,266,7089,6258,6775,366,307,7181,6445,91,6124,6986,5875,5869,305,6458,7064,6835,3068,6534,6113,3582,2773,2825,3078,2796,241,6096,596,6219,3018,4754,2891,6626,5763,3301,6961,4750,3282,1776,6952,2890,6183,2,6908,699,128,6727,5794,3186,5800,3253,2857,6500,2799,534,6930,637,6814,7070,6095,2952,222,6084,6013,4738,6090,6015,6925,7009,3635,3046,2791,5986,6275,6234,7052,7115,6825,6989,2920,3176,6346,6122,182,5822,7042,6716,3362,528,6670,4737,7154,3422,3047,563,6620,667,3602,481,3417,6724,6240,6762,5819,3138,438,382,6204,5951,5917,378,3717,6910,6156,6928,6786,6132,6581,6614,3378,5814,6217,6611,6998,97,6268,7151,3671,6589,589,6877,2925,5848,3702,6109,3054,6631,682,567,329,6121,6863,4719,6231,6691,6912,6415,6180,5974,507,3289,2951,419,664,3035,6331,551,6655,95,3107,519,7184,3357,172,3603,409,6495,3660,3101,2828,3646,6075,6980,6086,6976,6920,6091,249,6943,255,7141,3589,7131,3308,5873,3073,6473,296,6237,6325,6820,6256,5782,1774,3229,1747,1748,6329,2864,3374,90,2872,443,3682,2900,6617,5851,5799,3032,3133,6485,3608,526,393,524,3311,2800,498,6723,494,3689,3300,118,6462,2906,6222,6159,2879,488,6210,6818,360,1770,99,3057,3688,5839,6791,5828,2999,7105,5787,4740,3405,3210,6594,2916,2866,5924,6024,7108,6996,6927,2948,6756,6714,3030,2892,4743,675,6243,2782,696,264,5961,6424,2954,435,7019,5889,2807,6250,3274,6285,3659,6749,5773,3675,3398,7169,2894,3236,3583,6055,431,2961,6942,3351,5920,462,5833,6722,6975,5999,436,4725,6613,6921,6228,6954,6403,3664,3290,3069,711,3607,6623,2817,149,6575,3130,280,4751,6188,3686,88,5790,3243,461,3653,240,6773,3048,4723,5914,2792,6612,3549,650,6504,7156,7022,6881,5994,5908,6966,6020,3541,3137,7066,6447,6480,6186,3012,204,6339,6711,3259,3406,6248,6070,3342,6582,283,7165,6208,624,6616,6284,3131,681,361,7127,6177,5991,2971,7103,3306,2865,643,472,5967,444,3136,537,6028,3029,6233,252,3246,4733,6726,6648,5898,3714,1757,6721,3396,6249,199,369,243,6603,3632,3350,7010,6041,3693,6933,2986,7023,230,6388,3621,6440,6898,6151,6442,6044,3600,573,7104,6785,2816,6878,2881,5903,5826,6014,621,3623,5801,6129,4734,6475,5935,6596,6067,6246,3427,5802,5859,585,6498,1775,708,713,6601,3403,6328,6077,6005,2776,701,6796,5845,132,7102,6379,322,6652,6956,6503,2835,5893,7126,6844,6572,3082,3716,7163,6845,3654,6395,289,5779,6923,3288,583,6743,2938,3000,6420,5964,693,3345,527,3543,3391,6634,4730,6824,627,286,376,6733,5840,2963,147,5863,6236,6794,3019,6416,6269,2930,6423,3676,535,3720,642,6750,3056,3409,7148,3117,6527,6148,3275,6657,6907,6100,6136,6685,3690,2942,6671,6486,3645,7011,6931,6410,5960,3347,7050,3055,7142,2940,7122,3192,6850,5909,3710,6045,3157,430,7038,6894,3079,6467,3269,158,7018,6499,2918,6297,2943,3298,6463,459,2939,6968,3305,6597,6564,6406,492,3094,3596,544,6194,6667,6441,3159,6516,6860,606,5990,3669,308,6193,5817,6567,6117,2852,248,208,7180,614,6584,6365,94,6965,5805,6167,7087,5807,6739,7173,6382,6364,6230,7145,1752,2982,6487,6587,7160,6398,5987,477,7128,3344,2907,6887,6342,3299,5771,6454,3239,3010,406,6171,6512,3377,6033,5880,2813,6314,6290,6242,5906,5776,6088,5958,3231,2956,2841,6757,3652,6063,6874,3170,6184,6717,269,6318,1728,261,6220,3585,5877,5780,3162,3146,700,3237,6736,5948,587,3214,3191,7057,6408,3619,3175,6732,3193,349,2953,7161,5976,6929,6790,3426,6647,242,5784,3712,2991,677,2826,6774,6326,6083,3318,3154,96,7147,631,2831,6146,6040,107,6884,3366,6060,7027,5952,3642,5968,363,6771,661,512,6628,3077,523,6446,2911,6896,458,6745,6706,368,6349,3364,6768,6708,5992,538,6787,3050,6085,595,6978,6696,2998,5803,2815,5919,3058,6645,6474,6546,7001,7170,651,7053,6658,359,6988,6191,3009,2811,6547,3678,5796,6036,6661,6816,6310,3248,7014,2877,7025,1737,7183,6937,6936,6279,1721,2845,5846,3125,6126,3247,6385,4732,6264,2896,623,6857,6763,2777,6197,6574,3590,229,6687,3668,6674,3182,559,7157,304,2772,633,6876,6856,6134,6402,3395,6366,405,5983,6409,6656,6557,6897,6675,6293,679,375,680,408,6147,6006,2823,6568,4747,6488,3587,6506,6784,6886,3100,6693,3701,7062,2806,659,7060,6450,6302,6949,6609,279,3588,3584,6766,3698,6701,7182,3291,565,7179,6847,566,457,6751,6207,6639,553,5890,3424,302,6994,6196,6390,3416,5895,6309,6962,3367,6747,3370,3284,2989,6941,3315,6797,3385,398,6709,6627,1726,1732,6393,4745,6540,3226,5885,3059,2928,6478,2797,245,2901,6313,6135,3016,484,6489,558,1720,5923,1754,168,6684,598,471,3222,287,6023,2868,6453,3066,167,6164,5912,7021,290,3169,3084,6397,2886,6355,7051,3238,7095,3414,6497,367,6569,5835,4718,3361,7178,572,3184,6783,1735,3662,3027,5929,3333,423,6484,6728,2905,3087,5876,5852,672,6595,3267,3387,316,3065,2805,256,332,3091,3281,3145,6351,422,3108,6738,3268,5810,6828,3148,625,6663,5977,3699,617,5887,3106,6101,4757,3173,6713,3014,7084,3709,3181,3296,6683,426,6202,6094,2808,5837,7055,3213,6668,299,169,6635,2822,6181,605,3240,3135,6793,660,2871,3266,5945,236,3397,7082,6967,135,2970,5949,4752,1751,454,5864,3359,3085,3143,500,6405,2795,3440,2980,3203,2769,239,6306,4724,3188,561,6072,7101,3017,2937,6255,5764,2946,6531,7008,3013,6000,6607,244,5989,6508,468,1717,7026,1738,1725,6974,6642,3358,7069,6707,6069,6779,2842,6776,7012,6158,3681,3242,4758,3695,6565,6358,6209,6303,6443,6338,105,5894,6715,3187,6697,3410,3223,2947,2932,2844,6992,5892,6720,467,2887,3348,3302,6271,3219,6832,2978,339,600,6633,6125,6323,6782,5897,3279,335,3090,6700,6056,6935,1768,254,3156,377,7164,3704,3647,6142,7044,6334,7071,3655,6492,619,6754,2903,3102,5821,6780,517,5862,6221,6591,6311,129,203,6742,6542,6007,6118,7172,5996,3650,3297,3721,6963,6599,6815,6586,2889,511,6932,3319,6646,2914,7137,6307,3411,6472,6315,330,2929,451,323,348,6298,7065,3273,112,560,7033,6261,2944,613,268,6694,6116,1745,6938,3683,5884,3706,615,7135,3195,3151,601,7093,590,3074,2908,7099,7118,6804,7075,6185,7056,5768,3713,202,7111,6110,2974,610,6324,3252,678,3620,579,510,6529,7068,3083,6213,5950,6535,6765,6481,5841,6061,6944,212,6016,6735,6744,5937,6576,6161,6140,2913,489,6104,5936,515,7096,3286,3080,6254,2927,3310,706,7150,6836,2836,7063,6866,571,5815,3412,295,3610,1730,213,3637,3260,3335,6592,6984,594,6636,460,499,6022,265,5973,6662,3095,3256,6566,6058,568,4742,6192,413,7067,6669,6665,6372,649,100,3599,2933,6229,3696,421,3504,429,6456,6105,5878,6853,5911,6752,211,6834,6337,5793,6802,6421,6649,2902,6573,465,6872,3276,3039,5940,7046,6521,5954,6179,6477,3230,3309,6396,3612,6301,6862,539,2922,278,3658,6641,6251,5910,5772,271,6554,3042,3020,417,6218,5829,529,3031,390,6377,6066,6068,5932,5823,3211,6812,7003,5969,3703,6770,120,578,695,6154,6644,5939,7175,2785,6299,508,3373,5811,5907,3277,6526,5827,3617,3001,315,3639,2962,6664,6174,2921,5985,181,6970,3375,6374,7162,6417,1755,2827,6630,3244,6698,6017,5896,3591,400,6414,6425,3673,5849,6123,285,6758,6522,6292,4727,3190,6889,3098,6807,1718,7123,6548,3287,6018,5781,428,6426,3285,3061,6618,6173,3207,5792,3040,6381,164,6947,6593,6561,6286,186,5918,6619,5783,501,6865,6059,545,5806,3611,6429,6051,3355,6643,7110,143,496,1762,6332,228,7083,2829,668,5984,5758,7129,294,6830,7015,6273,6455,645,6004,607,258,6452,7073,483,6539,6449,6654,6523,5842,6926,3651,6817,5902,6281,646,379,320,115,6900,108,6764,7158,5813,3034,7000,127,5865,3196,5921,7028,7002,6753,5847,5820,3404,6119,3212,6869,3415,2987,6200,6370,676,153,6344,6362,3041,2839,3674,1753,3383,5857,3379,6294,532,6052,3392,6583,5871,259,5922,3251,6789,6677,6277,2945,324,6089,7120,6201,6078,3178,6532,6235,2965,2904,5760,2780,5854,3007,6901,7177,6800,418,3099,3715,7119,3233,6892,387,6855,3168,7176,3105,2967,690,6899,3263,2977,3380,6419,5775,2919,3666,3705,6972,7079,6971,683,317,702,6336,2988,126,6252,194,463,476,4735,374,6891,6864,3428,6916,6637,224,6801,2975,318,7136,6585,6778,3011,6918,6187,647,593,7143,6468,293,3419,3680,3692,495,3198,3563,154,3250,3249,657,6411,474,2969,6632,6076,6047,5915,351,116,3371,3592,1733,6748,5953,3384,2936,2979,6496,6530,6476,6653,6216,2819,7061,6102,3200,6471,1767,6434,6831,3633,3245,3167,3625,3616,6176,3160,7100,5816,4746,3634,3627,3220,626,6019,6239,3051,470,6541,6189,6081,2878,3134,478,577,505,6378,7144,6729,7081,6919,2768,328,525,6795,634,2995,6282,1771,5916,210,710,4722,6640,6253,6560,3081,6913,3679,3340,2766,6333,3128,6461,6483,6226,3129,6851,456,5838,6719,3604,6064,6032,6854,2837,3420,546,3028,6525,686,6262,303,4755,6991,1741,6672,2854,6985,6029,3597,3665,6457,89,3304,2781,3144,7112,3341,6827,6981,6327,497,3629,6278,6263,155,6911,6407,6368,2950,609,416,6624,195,3163,3354,3063,6021,441,6840,3067,6153,2924,6602,7091,6199,3685,7017,6760,6165,3224,2994,6335,2789,6890,2876,6904,6615,7031,6688,5757,7152,6223,6383,3399,2786,6359,503,4720,3185,6702,3613,3152,2923,2778,7109,5928,2824,5900,106,3194,6133,2899,6578,3356,3336,3234,6545,6384,6435,4753,6490,3026,2957,6157,3606,5926,2972,396,6272,6225,6848,6725,6012,514,6806,6833,6469,3053,2830,2767,389,6948,5850,3215,7005,6519,6371,114,3086,3614,3092,2873,6792,5767,6422,518,2996,6518,6690,6746,7116,6712,5997,98,2883,6266,2833,6821,3622,3025,7049,5963,235,3189,306,5979,5832,6997,338,2858,358,6695,412,6610,3312,6577,6319,6080,564,3317,2958,3618,5762,6459,5804,3121,232,5867,3158,6065,7155,6343,6160,131,3609,310,3033,3064,584,2935,3254,353,2861,6686,574,6074,5905,6145,6436,5778,6822,6037,6215,6046,6291,6054,5883,3002,3294,6524,7134,6870,3217,6470,6444,102,6392,6466,6144,3037,533,3116,7117,5899,6699,6875,6810,207,6570,6550,7124,6137,6973,3624,3258,6198,365,3687,704,6987,5866,3262,2775,157,3172,6803,6718,7045,618,6115,6689,5955,3542,6924,6953,6283,4726,3388,3400,5830,3643,392,191,6464,3368,6579,1736,6673,2931,671,3722,580,3719,6227,6205,7149,3630,6730,2949,6103,6621,4748,6678,3179,7088,6211,5933,2771,184,6413,5930,6960,6212,394,272,6163,3656,3177,190,6172,6650,6710,6680,6629,2870,5860,554,6039,6175,3402,2846,6071,424,6507,2809,7035,7013,670,6274,5993,309,4736,5882,3550,5934,509,6437,6043,156,641,475,6837,6276,2783,7040,2981,3307,6049,6819,6903,282,7094,502,6389,6676,6360,257,7121,6559,6479,7039,6809,3149,2984,6846,5765,6295,6882,3601,3218,6120,6097,2993,2992,6839,5836,506,6394,6245,6139,5978,6705,3707,331,6460,3206,599,7072,6951,7034,6376,2867,7036,7086,6031,6321,6308,5943,7140,2821,6600,2814,698,2910,6048,4739,466,6491,2853,6537,6300,5834,6428,6580,6502,446,6829,6433,6042,6555,219,6038,3667,231,6340,3153,7113,6400,414,6312,640,6241,3164,2917,2774,6868,6501,3072,6093,3393,5947,410,6934,6536,3005,6509,6873,6027,5774,3171,6001,381,597,6348,5988,548,3045,7114,6399,1769,6350,6551,6841,6588,709,177,6438,6155,3390,7125,6799,2856,2849,3425,110,2897,145,6131,557,6330,485,3180,2976,6003,2915,6858,2818,6823,6703,3394,3124,341,6520,170,6322,3227,712,1740,101,6826,473,6092,415,6011,5879,3142,6838,6666,5766,2985,6182,5870,689,3677,3303,6808,522,6513,674,630,6788,1723,575,6427,401,6638,7139,6244,6232,3174,6387,3711,5786,3649,7080,1743,6843,543,6482,5888,6772,3118,6867,3640,6590,3113,6270,2848,687,3209,2788,6958,6608,6361,3295,7133,2798,288,622,3216,7168,5972,6769,6320,2880,5868,635,2893,3421,151,4721,6759,3021,620,3257,6082,2838,205,6915,6034,3155,325,673,5995,6852,6605,7171,2874,3586,5946,540,3038,6514,6363,1739,5970,3313,6098,464,5956,277,1777,6289,3043,6895,300,6106,6079,2898,284,6465,3661,4729,3096,6553,6353,3023,2955,6995,3381,588,7058,2912,7166,439,206,1773,586,336,275,260,7167,2820,2869,6026,2959,6304,5965,3103,5789,3369,425,5971,6979,5891,6781,1734,5941,5886,403,6811,3280,654,7059,5770,3015,2801,549,6287,653,6203,2964,6206,6166,130,612,3657,3648,3141,7107,6731,6305,5913,552,1772,3628,6682,6805,5981,386,6959,6902,6767,433,364,5874,6493,345,92,541,2960,437,6909,3008,1719,197,6533,6128,7041,707,665,6267,482,7153,3197,2802,5957,5788,311,103,6162,2810,5901,6755,6418,4728,2888,420,6914,3691,5785,3389,6345,5938,6543,6679,6549,6168,1761,291,6347,6737,3003,2851,7098,6170,3127,602,3615,6280,3278,691,3272,6798,3123,6917,3147,3271,6556,705,3636,104,6993,3114,171,3255,1729,6893,397,604,427,3049,2934,347,3165,179,7085,562,6190,636,6969,6515,7037,5759,6704,3241,2973,3386,5966,6053,445,3139,3545,6604,5791,6448,7048,6073,3718,1724,253,692,6950,109,5904,3208,3076,3075,3334,133,6380,6316,6317,6025,3631,7078,6888,3232,603,383,3418,301,6940,6367,3261,5959,3161,6224,6692,3089,6357,432,2847,3104,6111,1763,7132,3413,319,3363,3684,6247,3036,6681,6494,7007,3228,3293,2779,3205,3052,6432,267,5925,6957,2941,146,3316,5858,5855,6288,6777,5881,582,6352,6964,7030,2860,2812,7006,3423,3060,2832,5812,3070,6880,6651,7074,262,6659,3360,7146,6296,337,6099,5809,504,6112,3343,238,6369,547,2966,7138,7077,569,124,5975,6260,6859,3372,4744,5931,6265,6510,7106,5980,685,5825,3365,3598,2895,2834,5824,2926,7097,6009,2884,3044,7047,5982,3115,3062,3401,6050,404,2862,6143,5962,6386,6150,6149,2909,6842,5797,3183,3166,2968,616,6552,3638,6955,7016,6412,5798,6622,2875,516,542,395,3004,274,3201,6562,5853,163,7024,6734,669,223,352,2885,3353,3006,6761,362,221,6606,6538,3270,688,6152,6195,6451,3376,2882,3024,111,6169,6008,5942,7076,5844,6813,6439,326,371,6558,6057,6356,6401,6741,6341,7092,6740,3408,3544,183,2997,150,7043,3225,2787,3314,3283,1731,5831,5944,6087,3022,648,570,3540,7029,6511,6391,6505,530,536,6982,6571,7004,3697,520,3221,148,2850';
//    $test1 = explode(',',$test);
//    $x = [];
//    $products = \Modules\Shop\Entities\Product::whereNotIn('id',$test1)->orderBy('id','desc')->get();
//    foreach ($products as $product){
//        array_push($x,$product->id);
//    }
//    dd(\App\Models\OldCategory::all());

    return 'Hi';
});

Route::get('/migrate', function () {
    \Artisan::call('migrate');
    dd('Migrating');
    return 'Test email sent.';
});
Route::get('import_category',[ImportController::class,'category']);
Route::get('import_product',[ImportController::class,'product']);
Route::get('import_product_image',[ImportController::class,'importProductImages']);
Route::get('import_product_category',[ImportController::class,'productCategory']);
Route::get('import_product_related',[ImportController::class,'productRelated']);
Route::get('import_product_kit',[ImportController::class,'kitInclude']);
Route::get('import_courses',[ImportController::class,'courses']);
Route::get('import_graduationProject',[ImportController::class,'graduationProject']);
Route::get('import_outlay',[ImportController::class,'outlay']);
Route::get('import_supplier',[ImportController::class,'supplier']);
Route::get('import_user',[ImportController::class,'user']);
Route::get('import_order',[ImportController::class,'order']);
Route::get('update-products-qty',[ImportController::class,'updateProductsQty']);
Route::get('slug',[ImportController::class,'slug']);
Route::get('location',[ImportController::class,'location']);
Route::get('location2',[ImportController::class,'locationNames']);
Route::get('elastic',[ImportController::class,'createProductIndex']);

Route::get('/test-elastic2', function() {
    $products = Product::where('id' ,'<' ,155)->get();
    foreach ($products as $product){
        $result[] = $product->toSearchableArray();
    }
    return $result;
});

Route::get('/raw-curl-test', function() {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "http://134.209.88.205:9200",
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "elastic:MIkro@123",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return $error ? ["cURL error" => $error] : json_decode($response, true);
});
Route::get('/product_sear', function() {
    $product = Product::first();
    $data = $product->toSearchableArray();
    $test =  $product->searchable();
    dd($data);
});

Route::get('/receipt-payment-method', function() {
    $receipts = Receipt::all();
    foreach ($receipts as $receipt){
        if ($receipt->type == 'CASH'){
            $receipt->payment_method_id = 1;
            $receipt->save();
        }elseif ($receipt->type == 'CHECK'){
            $receipt->payment_method_id = 5;
            $receipt->save();
        }elseif ($receipt->type == 'TRANSFER'){
            $receipt->payment_method_id = 4;
            $receipt->save();
        }

    }
    return $receipts;
});

// routes/web.php
Route::get('/test-single-index/{id}', function($id) {
    try {
        $product = \Modules\Shop\Entities\Product::find($id);
        $client = app('elasticsearch');

        $params = [
            'index' => $product->searchableAs(),
            'id' => $product->id,
            'body' => $product->toSearchableArray()
        ];

        $response = $client->index($params);

        return response()->json([
            'status' => 'success',
            'response' => $response->asArray()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'params' => $params ?? null
        ], 500);
    }
});







// routes/web.php
// routes/web.php
Route::get('/test-es-connection', function() {
    try {
        $client = app('elasticsearch');
        $response = $client->info();

        return response()->json([
            'status' => 'success',
            'data' => $response->asArray()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'host' => config('scout.elasticsearch.host'),
            'user' => config('scout.elasticsearch.user'),
            'password' => config('scout.elasticsearch.password'),
        ], 500);
    }
});

Route::get('/test-elastic', function() {
    // Test Elasticsearch client
    try {
        $client = app('elasticsearch');
        $info = $client->info()->asArray();
        $infoResult = "Elasticsearch connected! Version: ".$info['version']['number'];
    } catch (\Exception $e) {
        $infoResult = "Elasticsearch connection failed: ".$e->getMessage();
    }

    // Test indexing
    try {
        $product = \Modules\Shop\Entities\Product::first();
        $product->searchable();
        $indexResult = "Product #{$product->id} indexed successfully!";
    } catch (\Exception $e) {
        $indexResult = "Indexing failed: ".$e->getMessage();
    }

    return response()->json([
        'elasticsearch_connection' => $infoResult,
        'indexing_test' => $indexResult
    ]);
});

Route::get('/manual-index', function() {
    $client = app('elasticsearch');
    $product = \Modules\Shop\Entities\Product::first();

    $params = [
        'index' => env('ELASTICSEARCH_INDEX', 'test_productssss'),
        'id' => $product->id,
        'body' => $product->toSearchableArray()
    ];

    try {
        $response = $client->index($params);
        return response()->json([
            'status' => 'success',
            'data' => $response->asArray()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'params' => $params
        ], 500);
    }
});

Route::get('/debug-connection', function() {
    $host = config('scout.elasticsearch.host');
    $user = config('scout.elasticsearch.user');
    $password = config('scout.elasticsearch.password');

    $diagnostics = [
        'host' => $host,
        'user' => $user,
        'password' => $password,
        'parsed_host' => parse_url($host),
        'is_valid_url' => filter_var($host, FILTER_VALIDATE_URL) !== false,
        'curl_version' => curl_version()['version']
    ];

    try {
        $client = app('elasticsearch');
        $info = $client->info()->asArray();
        $diagnostics['connection_test'] = 'success';
        $diagnostics['elasticsearch_version'] = $info['version']['number'];
    } catch (\Exception $e) {
        $diagnostics['connection_test'] = 'failed: ' . $e->getMessage();
    }

    return response()->json($diagnostics);
});

Route::get('/test-http', function() {
    $url = config('scout.elasticsearch.host');
    $context = stream_context_create([
        'http' => [
            'header' => 'Authorization: Basic ' . base64_encode(
                    config('scout.elasticsearch.user') . ':' . config('scout.elasticsearch.password')
                )
        ]
    ]);

    try {
        $response = file_get_contents($url, false, $context);
        return response()->json([
            'status' => 'success',
            'response' => json_decode($response, true)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'url' => $url
        ], 500);
    }
});

Route::get('/hardcoded-test', function() {
    try {
        $client = ClientBuilder::create()
            ->setHosts(['http://134.209.88.205:9200'])
            ->setBasicAuthentication('elastic', 'MIkro@123')
            ->setSSLVerification(false)
            ->build();

        $product = \Modules\Shop\Entities\Product::first();

        $params = [
            'index' => env('ELASTICSEARCH_INDEX', 'test_productssss'),
            'id' => $product->id,
            'body' => $product->toSearchableArray()
        ];

        $response = $client->index($params);
        return response()->json($response->asArray());

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// routes/web.php

// Test custom indexing
Route::get('/test-custom-index', function() {
    $service = app('custom-elastic');
    $product = \Modules\Shop\Entities\Product::first();

    $response = $service->indexDocument(
        env('ELASTICSEARCH_INDEX', 'test_productssss'),
        $product->id,
        $product->toSearchableArray()
    );

    return response()->json($response);
});

Route::get('/test-fixed-index', function() {
    $service = app('custom-elastic');
    $product = \Modules\Shop\Entities\Product::first();

    // Get the searchable array with fixes
    $data = $product->toSearchableArray();

    // Log the data for inspection
    \Log::debug('Indexing data', $data);

    $response = $service->indexDocument(
        env('ELASTICSEARCH_INDEX', 'test_productssss'),
        $product->id,
        $data
    );

    return response()->json($response);
});

// Test search
Route::get('/test-custom-search', function() {
    $service = app('custom-elastic');

    $response = $service->search(env('ELASTICSEARCH_INDEX', 'test_productssss'), [
        'query' => [
            'match_all' => new \stdClass()
        ]
    ]);

    return response()->json($response);
});
Route::get('/test-category-slugs', function() {
    $product = \Modules\Shop\Entities\Product::with('categories')->first();

    return [
        'product_id' => $product->id,
        'category_slugs' => $product->categories->pluck('slug'),
        'in_elastic' => $product->toSearchableArray()['category_slugs']
    ];
});



Route::get('/welcome', function () {
    $order = Order::find(209);

    return view('test.order_details', compact('order'));
});
Route::get('/putproductnameintheorder', function () {
    // Get all orders
    $orders = Order::with('products')->where('id','>=',3000)->get();

    foreach ($orders as $order) {
        // Loop through each product in the order
        foreach ($order->products as $product) {
            // Update the product_name in the pivot table
            $order->products()->updateExistingPivot($product->id, [
                'product_name' => $product->name,
            ]);
        }
    }

    return "Product names updated for all orders.";
});
Route::get('/putproductnameintheinvoice', function () {
    // Get all orders
    $invoices = Invoice::with('products')->get();

    foreach ($invoices as $invoice) {
        // Loop through each product in the order
        foreach ($invoice->products as $product) {
            // Update the product_name in the pivot table
            $invoice->products()->updateExistingPivot($product->id, [
                'product_name' => $product->name,
            ]);
        }
    }

    return "Product names updated for all Invoices.";
});

Route::get('/encrypt/{id}', function ($id) {
//    dd($id);
    // Encrypt the given ID using bcrypt
    return bcrypt($id);
});

Route::get('/generateUuid', function () {
    $orders = Order::where('uuid',null)->get();
    foreach ($orders as $order){
        $order->uuid = Str::uuid();
        $order->save();
    }
});

Route::get('/fixSearchForColors', function () {
    $colors = ProductVariant::all();
    foreach ($colors as $color){
        $parentProduct = Product::find($color->product_id);
        $product = Product::find($color->color_id);
        $product->is_show_for_search = false;
        $parentProduct->colors_nick_names .= ' , ' . $color->name . ' , ' . $product->name;
        $parentProduct->save();
        $product->save();
    }
});
