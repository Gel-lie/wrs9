# chatbot.py
from flask import Flask, request, jsonify
import re
import random

app = Flask(__name__)

# --- INTENTS DATABASE ---
intents = {
  "intents": [
    {
      "tag": "greeting",
      "patterns": ["hi", "Hi", "HI", "hI", "Hi!", "hi!", "Hii", "Hiii", "hii", "hi po", "Hi po", "hi poh", "hi poh!", "hi po!", "hii po", "Hii po", "HI PO", "HiPO", "hiPO!",
"hello", "Hello", "HELLO", "helloo", "helo", "heloo", "hellooo", "hello po", "Hello po", "helo po", "hello poh", "hello poh!", "hellow", "hellow po", "Helloo!", "HeLLo!", "hElLo", "HELLO!",
"hey", "Hey", "HEY", "heyy", "heyyy", "heyyyy", "hey po", "Hey po", "heyy po", "hey poh", "hey!!", "hey!", "hey there!", "hey po!", "hEy", "HeY!",
"yo", "Yo", "YO", "yo!", "Yo!", "YoYo", "yo po", "yo poh", "yoh!", "yoo!", "YoPo!", "Yo po!", "YO!!",
"good morning", "Good Morning", "GOOD MORNING", "gud morning", "gud moring", "goood morning", "good morning po", "Good morning po", "gud morning po", "good mornin po", "good mOrNiNg", "GOod mORNing", "gUd mOrNiNg", "good morning!",
"good afternoon", "Good Afternoon", "GOOD AFTERNOON", "gud afternoon", "good afternun", "good afternon", "good afternoon po", "Good afternoon po", "gud afternoon po", "goOD aFTErnoOn", "good afternoon!", "GOOD AFTERNOON!",
"good evening", "Good Evening", "GOOD EVENING", "Gud Evening", "good evenin", "good evening po", "Good evening po", "Gud evening po", "evening po", "GoOd EveNinG", "good evening!", "GOOD EVENING!",
"good night", "Good Night", "GOOD NIGHT", "gn", "GN", "Gud Nyt", "Good night po", "good nyt po", "gud nyt", "good night!", "GOOD NIGHT!",
"kamusta", "Kamusta", "KAMUSTA", "kumusta", "Kumusta", "KUMUSTA", "musta", "Musta", "MUSTA", "musta po", "kamusta po", "kumusta po", "musta na", "Kamusta ka", "Kamusta na", "Kumusta ka na", "Kumusta na po", "mMusta", "mmmusta", "mmMusta",
"magandang umaga", "Magandang Umaga", "MAGANDANG UMAGA", "magandang umaga po", "Magandang umaga po", "mgaandang umga", "magndang umaga", "mgaNdang uMAga", "magndAng UmAgA",
"magandang hapon", "Magandang Hapon", "MAGANDANG HAPON", "magandang hapon po", "Magandang hapon po", "magndang hapon", "mgaNdanG hAPOn",
"magandang gabi", "Magandang Gabi", "MAGANDANG GABI", "magandang gabi po", "Magandang gabi po", "magndang gabi", "mggandang gabi", "MaGaNdAng gAbi",
"greetings", "Greetings", "GREETINGS", "greetings po", "greetings poh", "greetz", "Gretings",
"welcome", "Welcome", "WELCOME", "Welcome po", "welcome po", "welkam", "welcum", "welcom po", "welkam po", "welkom",
"anong balita", "Anong Balita", "ANONG BALITA", "mustang araw mo", "musta araw mo", "kamusta araw mo", "kamusta ka", "kumusta ka", "kamusta ka po",
"pasensya na", "patawad", "excuse me", "Excuse Me", "EXCUSE ME", "excuse me po", "excuse po", "pasensya na po", "Pasensya po", "Excuse po",
"help", "Help", "HELP", "help po", "helpp", "halp", "plz help", "pls help", "paki help", "pakihelp", "pahingi ng tulong", "patulong", "Patulong", "PATULONG", "pahelp", "Pahelp", "PAHELP", "pa help", "pa Help", "pahlep", "pahulp", "patuLong",
"tulong", "Tulong", "TULONG", "tulong po", "tulong poh", "patulong po", "paki tulong", "paki tulong po", "tulungan mo ako", "Tulungan mo ako", "TULUNGAN MO AKO", "tulungan mo ako po", "paki tulungan ako",
"can you help me", "Can You Help Me", "CAN YOU HELP ME", "can u help me", "pwede mo ba akong tulungan", "Pwede mo ba akong tulungan", "PWEDE MO BA AKONG TULUNGAN", "pde mo ba ako tulungan", "pwede po ba tulong", "pwede po ba kayo tumulong", "pede po bang tulungan nyo ako", "paki tulong naman", "paki tulong naman po",
"need help", "Need Help", "NEED HELP", "kailangan ko ng tulong", "Kailangan ko ng tulong", "KAILANGAN KO NG TULONG", "kailangan ng tulong", "kailangan ko tulong", "kelangan ng tulong", "kailangan ko ng help",
"ask", "Ask", "ASK", "ask po", "ask?", "Ask?", "gusto ko magtanong", "Gusto ko magtanong", "GUSTO KO MAGTANONG", "pwede magtanong", "Pwede magtanong", "PWEDE MAGTANONG", "pwede po magtanong", "pde magtanong", "pwede ako magtanong", "may itatanong lang", "May itatanong lang",
"hi there", "Hi There", "HI THERE", "hi_there", "hi-there", "hi there po", "hi there!", "hi ther",
"hello there", "Hello There", "HELLO THERE", "hello there po", "hello_there", "hello-there",
"hey there", "Hey There", "HEY THERE", "hey there po", "hey_there", "hey-there",
"good day", "Good Day", "GOOD DAY", "good_day", "good-day", "magandang araw", "Magandang Araw", "MAGANDANG ARAW", "magandang araw po",
"morning", "Morning", "MORNING", "mornin", "mornin po", "morn", "morn po",
"afternoon", "Afternoon", "AFTERNOON", "hapon", "Hapon", "HAPON", "hapon po", "gud afternun po",
"evening", "Evening", "EVENING", "gabi", "Gabi", "GABI", "gabi po", "good eves", "goodeve", "good eve po",
"yo!", "hey!!", "hi!!", "hello??", "hi??", "hey??", "hello po??", "kamusta??", "patulong??", "pahelp??", "pls help??", "help??",
"GOod mORNing", "goOD aFTErnoOn", "GoOd EveNinG", "hElLo", "HeLLo", "HeY", "yO", "gUd MoRnIn", "hI", "HeLp", "Plz HeLp", "hELp Me", "HeLp mE",
"mgaNdang uMAga", "mgaNdanG hAPOn", "MaGaNdAng gAbi", "pAkI tULonG", "paHeLp", "kAmuSta", "KuMuSta", "mUsTa", "TuLoNg"
],
      "responses": ["Good day, Mam/Sir, How can I help you today?"]
    },
    {
      "tag": "pricing",
      "patterns": ["price", "cost", "how much", "product price"],
      "responses": [" hi Our products vary in price. Could you please specify whether you want to refill or buy a product?"]
    },
    {
      "tag": "refill",
      "patterns": ["refill", "Refill", "REFILL", "refil", "rephil", "re-fill", "re fill", "rfil", "refill po", "Refill po", "refill poh", "Refill poh", "refill po!", "refil po", "rephil po", 
"pa refill", "Pa refill", "pa Refill", "Pa Refil", "Pa refill po", "pa refill poh", "pa refill po!", "paki refill", "paki Refill", "paki refill po", "paki Refil", 
"magparefill", "Magparefill", "magpa refill", "Magpa refill", "magpa Refil", "mag pa refill po", "mag pa refill poh", "magparefil", "magpa rephil", "magparephil", 
"tubig", "Tubig", "TUBIG", "tubeg", "tubg", "tubigg", "tubigg po", "tubig po", "Tubig po", "tubig poh", "tubig poh!", "tubig po!", "tubig please", "tubig pls", 
"pa tubig", "Pa tubig", "pa tubig po", "Pa tubig po", "paki tubig", "paki tubig po", "pakitubig", "Pakitubig", "pakitubig po", "pahingi tubig", "pahingi ng tubig", 
"kailangan ng tubig", "Kailangan ng tubig", "kailangan tubig", "need tubig", "Need tubig", "need water", "need more water", "need tubig po", "need more tubig", 
"dagdag tubig", "Dagdag tubig", "DAGDAG TUBIG", "dagdag tubeg", "dagdag tubig po", "Dagdag tubig po", "dagdag tubig poh", "dagdag tubig po!", "paki dagdag tubig", 
"pakidagdag tubig", "pakidagdag tubig po", "paki dagdag tubig po", "dag dag tubig", "dag dag tubig po", "pahingi dagdag tubig", "pa dagdag tubig", "pa dagdag tubig po", 
"magdagdag tubig", "Magdagdag tubig", "mag dagdag tubig", "Mag dagdag tubig", "magdagdag tubig po", "mag dagdag tubig po", "mag dagdag ng tubig", "mag dagdag pa tubig", 
"salin", "Salin", "SALIN", "salen", "salinn", "salen po", "salin po", "Salin po", "salin poh", "salin poh!", "salin po!", "pa salin", "Pa salin", "pasalin", "Pa salin po", 
"paki salin", "Paki salin", "pakisalin", "pakisalin po", "paki salin po", "salinan", "Salinan", "salinan po", "salinan poh", "salinan po!", "mag salin", "Mag salin", 
"mag salin po", "mag salin poh", "mag salin tubig", "mag salin ng tubig", "mag salin po tubig", 
"lipat", "Lipat", "LIPAT", "lpat", "lipat po", "Lipat po", "lipat poh", "lipat po!", "lipat poh!", "pa lipat", "Pa lipat", "pa lipat po", "Pa lipat po", "paki lipat", 
"paki Lipat", "pakilipat", "pakilipat po", "pakilipat poh", "lipatan", "Lipatan", "lipatan po", "maglipat", "Maglipat", "maglipat po", "mag lipat", "Mag lipat", 
"mag lipat po", "lipat tubig", "Lipat tubig", "lipat ng tubig", "lipat tubig po", "lipat tubig poh", "lipat po tubig"
],
      "responses": ["Round (5 gallons) = Php 30.00 ""Slim (5 gallons) = Php 30.00   ...... 10 Liters = Php 20.00 ........................    8 Liters = Php 20.00 ..............  7 Liters = Php Php 14.00 ..............  6.6 = Php 13.00 .............. 6 Liters = Php 12.00..............  5 Liters = Php 10.00.............."   ]
    },

    {
      "tag": "price",
      "patterns": ["bottle", "container", "containers", "bottles", "dipenser", "dispensers"],
      "responses": ["5 Gallons Slim: P 250.00"
      " 5 Gallons Round: P 250.00 i case 350 ml bottled water: P 384.00 1 case 500 ml bottled water: P 456.00 Hot and Cold Water Dispenser: P 7,000.00"]
    },
    {
      "tag": "opening_hours",
      "patterns": 
      ["opening hours", "Opening Hours", "OPENING HOURS", "openning hours", "openng hours", "opennig hours", "open hours", "openhour", "open hours po",
        "Opening hours po", "OPEN HOURS PO", "open hours poh", "openhours?", "open hours?", "Opening Hours?", "OPEN HOURS?", "open hour?", "open hourz",
        "open hourss", "opennng hoursss", "oPeninG HoUrS", "oPEning houRS", "OpEninG HoURz", "opEning hOurs po", "OPENNing Hours", "OPENiNg HOURS!!", 
        "Opening Hours!!", "open hours!!", "open ours", "open ours?", "openng ours", "openin ours", "Openng hours", "openng hours po", "open hours po?",
        "opening hour po?", "openin hour po", "opening hour?", "opening ours", "openin ours po", "Opening ours po", "OPENING HOURSSS", "open hoursss!!", 
        "openning hoursss!", "opennig ours!", "opennng hours!", "open our", "Open our", "OPEN OUR", "openour", "openour po", "openour poh", "openour po?",
        "openour?", "open hour!", "Opening hour!", "OPENING HOUR!", "OPENING HOURSS!", "Opening Hours Po", "Opening hours poh!", "open hours poh!", 
        "openin hours po", "Openin hourz po", "openhourz po", "open hourz po", "openourz po", "Opening hoursss", "Openin hourss", "Opnng hours", "Openng Hours",
        "opnng hourss", "openng hourzz", "opning hourz", "opnin hours", "Openin Hours", "OpEnInG hOuRs", "oPENiNG houRS", "Opening hOURss", "Openning houurss",
        "openinnng hourss!", "openin ourz po!", "Opening ours poh!", "time open", "Time Open", "TIME OPEN", "tym open", "tym opn", "time opn", "tym open po", 
        "time open po", "time open poh", "time open poh?", "time open po?", "time open?", "time open!", "Time Open!", "TIME OPEN!", "Time open?", "Tym open?", 
        "time open now?", "time open today?", "time open now po?", "time open po now?", "time open po today?", "time open po ba?", "time open ba?", "time open ba po?",
        "time open po ba?", "time open ba poh?", "time open poh ba?", "time opn po?", "tym opn po?", "tym open poh?", "tym opn poh", "tym opn?", "tym open?", "time oopen", 
        "time oopenn", "tme open", "tyme open", "time oppen", "time oppennn", "time opennn", "tme opn", "tym open", "tyme opn", "timee open", "Timee Open", "TiMe OpEn", 
        "tIME opEN", "tIme opEn", "TIME OPeN", "time OPEN", "time OPen", "time opEN!", "Time OPen!", "time openN!", "time OPENN!", "Time OPEN po", "time OPEN po!", 
        "time openn po!", "time openn poh!", "time oppen po!", "time openn!!", "Time openn!!", "TIME OPENN!!", "Tym OPEN", "tym OPEN po!", "tym openn po!", "tyme oppen po!",
        "tym oppen!", "time openn po", "time open poh po", "tym open poh po", "time open po poh", "time open po na?", "time open na po?", "open time?", "Open time", "OPEN TIME",
        "open tym", "open tym po", "open tym poh?", "open time poh?", "open time po?", "open time po ba?", "open time ba?", "open time ba po?", "open time today?", "open time now?",
        "open time po today?", "open tym today?", "open tym po?", "open tym poh?", "open tym po ba?", "tym open po ba?", "open tym?", "Open Tym?", "OPEN TYME?", "OPEN TIME!",
        "OPEN TYM!", "open timee!", "Open timee!", "timee open!", "timeee open!", "timeeee open!", "time open!!", "TIME OPEN!!", "open time!!", "OPEN TIME!!", "open tym!!", "OPEN TYM!!",
    "what time you open", "What time you open", "WHAT TIME YOU OPEN", "wat time you open", "wat tym u open", "what tym u open", "wat time u open", "wht time you open", "wat time open kayo",
      "wat time open po", "wat time open po ba", "wat tym open po", "wat tym open poh", "wat tym open poh?", "wat time open?", "what time open?", "what time you open?", "what time u open?", 
      "wat time u open?", "wat tym u open?", "wat time open po?", "what time open po?", "what time open poh?", "wat tym open po?", "wat tym open poh?", "wat time open po ba?", "what time open po ba?",
        "what time open ba po?", "wat time open ba?", "wat tym open ba?", "wat time open kayo?", "wat tym open kayo?", "what tym open kayo?", "what tym open kayo po?", "what time open kayo po?", 
        "what tym open po kayo?", "wat tym open po kayo?", "wat time open po kayo?", "wat time open po kayo ba?", "what time u open po?", "what time u open poh?", "wat time u open po?", 
        "wat tym u open po?", "wat tym u open poh?", "wat tym u open poh?", "wat tym open po kayo?", "wat tym open po ba?", "wat tym open po today?", "what tym open po today?", "wat tym open po now?",
          "what time u open now?", "what time u open today?", "what time open today?", "wat time open today?", "what time open po today?", "wat time open po today?", "what time open po ngayon?", 
          "wat time open po ngayon?", "wat tym open po ngayon?", "wat tym u open po ngayon?", "what tym u open po ngayon?", "wat tym u open poh ngayon?", "wat tym u open po na?", "wat tym open po na?", 
          "wat tym open na po?", "wat time open na po?", "what time open na po?", "wat time open po na?", "what tym open po ba?", "wat tym open po ba?", "wat tym open poh ba?", "wat time open poh ba?",
            "wat tym u open ba?", "wat tym u open ba po?", "wat tym u open poh ba?", "wat tym u open poh po?", "wat tym u open poh po?", "wat tym u open poh po!", "what tym u open poh po!", 
            "what time u open poh po!", "wat tym open poh po!", "wat tym open poh po!", "wat tym open poh po!!", "wat time open poh po!!", "what time open poh po!!", "what time open po!!", 
            "wat tym open po!!", "wat tym open po!!", "wat tym open po!", "wat time open po!", "wat time open po!!", "wat tym open po!!", "what time u open po!!", "what tym open po!!", 
            "What Time You Open!!", "WHAT TIME YOU OPEN!!", "Wat Tym You Open!!", "wat tym u opennn?", "wat tym u opennn po?", "wat tym u openn po?", "wat tym u oppen po?", "wat tym u open po!!", 
            "wat tym u oppen po!!", "what tym you open?", "What Tym You Open?", "WHAT TYM YOU OPEN?", "wat tym u openn!", "wat tym open?", "what time you openn!", "wat tym open po!!!", 
            "wat tym open po na!!", "wat tym open po now!!", "wat tym open po today!!", "wat tym open po ngaun!!", "what tym open po ngaun!!", "wat tym open ngaun po!!", "wat tym open po na!!",
              "wat tym open na po!!", "wat tym open poh na!!", "wat tym open poh na po!!", "wat tym open poh po!!", "wat tym open poh po!", "wat tym open poh po!", "wat tym open poh po!!",
                "wat tym open poh po!!!", "wat tym open poh po!!!!", "wat tym open poh po!!!!!", "wat tym open poh po!!!", "wat tym open poh po!!!", "what tym open poh po!!!",
                  "what time u open poh po!!!", "what time open poh po!!!", "wat time open poh po!!!", "wat tym open poh po!!!", "wat tym open poh po!!!", "wat tym open poh po!!!", "what time you open poh po!!!",
    "schedule", "Schedule", "SCHEDULE", "skedule", "skdule", "schedul", "sched", "sched po", "sched poh", "sked po", "sked poh", "sked?", "sched?", "schedule?", "Schedule?", "SCHEDULE?", "schedul?", "skedule?",
      "skdule?", "sched!!", "sched!!!", "SCHED!!!", "schedul po", "schedul poh", "sked po ba?", "sched po ba?", "sched poh ba?", "sked po ba?", "sched po today?", "sched po now?", "sched po ngaun?", 
      "sched po ngaun ba?", "sked po ngaun ba?", "sked ngaun po?", "sched ngaun po?", "sched po ngayon?", "sched po today?", "sked po today?", "sched today po?", "schedule po?", "Schedule po?", "SCHEDULE po?",
        "schedule po ba?", "sched po ba?", "sched po ba?", "schedule po ba?", "schedule ba po?", "sched po ba?", "sched po na?", "sched po now?", "sched po now ba?", "sched po ngaun?", "sched po today?",
          "sched po today ba?", "sched po?", "sched poh?", "sched po!", "sched poh!", "sched po!!", "sched poh!!", "sched poh po!!", "sched poh po!!!", "sched poh po!!!", "sched poh po!!!", "sched poh po!!!", 
          "schedule po!", "Schedule po!", "SCHEDULE PO!", "Sched po!", "Sched po!", "SCHED PO!", "SCHED Po", "schedPO", "SchedPO", "schedPO!", "schedPO!!", "SCHEDpo!!", "SkedPO!", "Skedpo!", "Skedpo!!",
            "SkedPO!!", "Sked po!!", "Sched poh!", "Sched poh!", "Sched poh po!", "Sched poh po!", "sched poh po!", "sched poh po!", "sched poh po!", "sched poh po!", "sched poh po!!", "sched poh po!!!", 
            "sched poh po!!!", "sched poh po!!!", "kelan bukas", "Kelan Bukas", "KELAN BUKAS", "kelan bkas", "kelan bukass", "kelan bkaas", "kellan bukas", "kellan bukaz", "kelann bukas", "kelann bukaz",
              "kelann bukazz", "kelan bukaz", "kelen bukas", "klen bukas", "kln bukas", "kln bkas", "kelan bukas po", "Kelan bukas po", "kelan bukas poh", "Kelan bukas poh", "kelan bukas po?", "kelan bukas po ba?", 
              "kelan bukas ba?", "kelan bukas ba po?", "kelan bukas po ba?", "kelan bukas po na?", "kelan bukas po ngaun?", "kelan bukas po ngayon?", "kailan bukas", "Kailan Bukas", "KAILAN BUKAS", "kailan bukas po",
                "Kailan bukas po", "kailan bukas poh", "kailan bukas po ba?", "kailan bukas ba po?", "kailan bukas po ngaun?", "kailan bukas ngaun?", "kailan bukas ngayon?", "kailan bukas po ngayon?", "kailan bukas po today?",
                  "kelan bukas po today?", "kelan bukas today?", "kelan bukas po today?", "kelan bukas po ngaun?", "kelan bukas ngaun?", "kln bukas po?", "kln bukas po ba?", "klen bukas po?", "kelen bukas po?", 
                  "kelan bukas po!!", "Kelan Bukas!!", "KELAN BUKAS!!", "KELAN BUKAS!", "kelan bukas!", "kelan bukas?", "Kelan Bukas?", "KELAN BUKAS?", "kelan bukas po!", "kelan bukas poh!", "kelan bukas poh!!",
                    "kelan bukas poh po", "kelan bukas poh po?", "kelan bukas poh po!", "kailan bukas poh po!", "kailan bukas poh po?", "kailan bukas poh po!!", "kailan bukas poh po!!!", "kelan bukas poh po!!!",
                      "kelan bukas poh po!!!", "kelan bukas poh po!!!", "bukas", "Bukas", "BUKAS", "bkas", "bkaas", "bukass", "bukazz", "bukaz", "bukazz!", "bukazz!!", "bukaaz", "bukaas", "bukasss", "bukassss", 
                      "bukazzss", "bukassz", "bukass!", "bukas!", "Bukas!", "BUKAS!", "bukas!!", "BUKAS!!", "bukas!!!", "BUKAS!!!", "bukas po", "Bukas po", "BUKAS PO", "bukas poh", "Bukas poh", "bukas poh po", 
                      "bukas po poh", "bukas po?", "bukas poh?", "bukas po ba?", "bukas ba?", "bukas ba po?", "bukas po ba?", "bukas po ba?", "bukas po ba today?", "bukas po today?", "bukas today?", "bukas po ngaun?", 
                      "bukas po ngayon?", "bukas ngaun po?", "bukas ngaun po ba?", "bukas po ngaun ba?", "bukas po na?", "bukas po na ba?", "bukas na po?", "bukas na?", "Bukas na", "BUKAS NA", "bukas na po", "Bukas na po",
                        "BUKAS NA PO", "bukas na poh", "bukas na poh po", "bukas na po?", "bukas po na?", "bukas po na ba?", "bukas po ba?", "bukas po ba?", "bukas poh po ba?", "bukas poh po?", "bukas poh po!", 
                        "bukas poh po!!", "bukas poh po!!!", "bukas poh po!!!!", "bukas poh po!!!!!", "bukas poh po!!", "bukas poh po!", "bukas poh po!", "bukas poh po!!!", "bukas poh po!!!", "BUKAS POH PO!!!",
                          "bukas po poh!!!", "bukas poh!!!", "bukas poh!!", "bukas po!!", "bukas po!!!", "bukas poh po!!", "bukas poh po!!!", "bukas poh po!!!!", "bukas poh po!!!!!"
],
      "responses": ["We are open from 8:00 AM to 8:00 PM, Monday to Saturday."]
    },
    {
      "tag": "rewards",
      "patterns": ["points", "rewards", "how does the point system work"],
      "responses": ["Our pointing system allows you to earn points for every purchase. Points can be redeemed for discounts or gifts."]
    },

     {
      "tag": "history",
      "patterns": ["history", "History", "HISTORY", "his3tory", "histry", "hystory", "histori", "history?", "History?", "HISTORY?", "history po?", "history po ba?", "history po ba?", "history ba?", "history ba po?", "history po ba?", "history po?", "history poh?", "history poh po?", "history poh po!", "history poh po!!", "history poh po!!!", "history po na?", "history po ngaun?", "history po ngayon?", "history po today?", "history po ngaun ba?", "history po ngayon ba?", "history po na ba?", "history po poh?", "history po poh ba?", "histry po?", "histry po ba?", "histry poh?", "histry poh po?", "histry po ba?", "histry po ngaun?", "histry po ngayon?", "histry po today?", "histry po ngaun ba?", "histry po ngayon ba?", "histry po na?", "histry po na ba?", "histry po poh?", "histry po poh ba?",
    "may ari", "may-ari", "May-ari", "MAY-ARI", "may arii", "may ari?", "may-ari?", "may ari po?", "may-ari po?", "May-ari po?", "MAY-ARI PO?", "may ari poh?", "may ari poh po?", "may ari po ba?", "may ari ba?", "may ari ba po?", "may ari po ba?", "may ari po ngaun?", "may ari po ngayon?", "may ari po today?", "may ari po ngaun ba?", "may ari po ngayon ba?", "may ari po na?", "may ari po na ba?", "may ari po poh?", "may ari po poh ba?", "may ari poh po?", "may ari poh po!", "may ari poh po!!", "may ari poh po!!!", "may ari poh po!!!!", "may ari poh po!!!!!", "may ari poh po!!", "may ari poh po!!!", "may ari poh po!!!!", "may ari poh po!!!!!", "may ari po poh po!!!", "may ari po poh po!!!!", "may ari po poh po!!!!!", "may ari poh po?", "may-ari po ngaun?", "may-ari po ngayon?", "may-ari po today?", "may-ari po ngaun ba?", "may-ari po ngayon ba?", "may-ari po na?", "may-ari po na ba?", "may-ari po poh?", "may-ari po poh ba?", "may-ari poh po?", "may-ari poh po!", "may-ari poh po!!", "may-ari poh po!!!", "may-ari poh po!!!!", "may-ari poh po!!!!!",
    "owner", "Owner", "OWNER", "ownr", "owneer", "owener", "owner?", "Owner?", "OWNER?", "owner po?", "owner po ba?", "owner ba?", "owner ba po?", "owner po ba?", "owner po?", "owner poh?", "owner poh po?", "owner poh po!", "owner poh po!!", "owner poh po!!!", "owner po ngaun?", "owner po ngayon?", "owner po today?", "owner po ngaun ba?", "owner po ngayon ba?", "owner po na?", "owner po na ba?", "owner po poh?", "owner po poh ba?", "ownr po?", "ownr po ba?", "ownr poh?", "ownr poh po?", "ownr po ba?", "ownr po ngaun?", "ownr po ngayon?", "ownr po today?", "ownr po ngaun ba?", "ownr po ngayon ba?", "ownr po na?", "ownr po na ba?", "ownr po poh?", "ownr po poh ba?", "owner po poh po!!!", "owner po poh po!!!!", "owner po poh po!!!!!",
    "kelan nag simula", "Kelan nag simula", "KELAN NAG SIMULA", "kilan nagsimula", "kilan nagsimla", "kelan nagsimula?", "Kelan nagsimula?", "KELAN NAGSIMULA?", "kelan nagsimula po?", "Kelan nagsimula po?", "kelan nagsimula poh?", "kelan nagsimula po ba?", "kelan nagsimula ba?", "kelan nagsimula ba po?", "kelan nagsimula po ba?", "kelan nagsimula po ngaun?", "kelan nagsimula po ngayon?", "kelan nagsimula po today?", "kelan nagsimula po ngaun ba?", "kelan nagsimula po ngayon ba?", "kelan nagsimula po na?", "kelan nagsimula po na ba?", "kelan nagsimula po poh?", "kelan nagsimula po poh ba?", "kelan nagsimula poh po?", "kelan nagsimula poh po!", "kelan nagsimula poh po!!", "kelan nagsimula poh po!!!", "kelan nagsimula poh po!!!!", "kelan nagsimula poh po!!!!!", "kilan nagsimula po?", "kilan nagsimula poh?", "kilan nagsimula po ba?", "kilan nagsimula ba?", "kilan nagsimula ba po?", "kilan nagsimula po ba?", "kilan nagsimula po ngaun?", "kilan nagsimula po ngayon?", "kilan nagsimula po today?", "kilan nagsimula po ngaun ba?", "kilan nagsimula po ngayon ba?", "kilan nagsimula po na?", "kilan nagsimula po na ba?", "kilan nagsimula po poh?", "kilan nagsimula po poh ba?", "kilan nagsimula poh po?", "kilan nagsimula poh po!", "kilan nagsimula poh po!!", "kilan nagsimula poh po!!!",
    "business", "Business", "BUSINESS", "bussiness", "busiess", "busness", "bisness", "bussines", "bussiness?", "bussiness po?", "bussiness po ba?", "bussiness ba?", "bussiness ba po?", "bussiness po ba?", "bussiness po?", "bussiness poh?", "bussiness poh po?", "bussiness poh po!", "bussiness poh po!!", "bussiness poh po!!!", "bussiness po ngaun?", "bussiness po ngayon?", "bussiness po today?", "bussiness po ngaun ba?", "bussiness po ngayon ba?", "bussiness po na?", "bussiness po na ba?", "bussiness po poh?", "bussiness po poh ba?", "bussiness poh po?", "bussiness poh po!", "bussiness poh po!!", "bussiness poh po!!!", "bussiness poh po!!!!", "bussiness poh po!!!!!", "busness po?", "busness po ba?", "busness poh?", "busness poh po?", "busness po ba?", "busness po ngaun?", "busness po ngayon?", "busness po today?", "busness po ngaun ba?", "busness po ngayon ba?", "busness po na?", "busness po na ba?", "busness po poh?", "busness po poh ba?", "busness poh po?", "busness poh po!", "busness poh po!!", "busness poh po!!!", "busness poh po!!!!", "busness poh po!!!!!",
    "people", "People", "PEOPLE", "peple", "ppl", "peepol", "pepol", "pipol", "people?", "people po?", "people po ba?", "people ba?", "people ba po?", "people po ba?", "people po?", "people poh?", "people poh po?", "people poh po!", "people poh po!!", "people poh po!!!", "people po ngaun?", "people po ngayon?", "people po today?", "people po ngaun ba?", "people po ngayon ba?", "people po na?", "people po na ba?", "people po poh?", "people po poh ba?", "pepol po?", "pipol po?", "pipol poh?", "peepol po?", "peepol poh?", "pepol po ba?", "pipol po ba?", "peepol po ba?", "pepol po ngaun?", "pipol po ngaun?", "peepol po ngaun?", "pepol po ngayon?", "pipol po ngayon?", "peepol po ngayon?", "pepol po today?", "pipol po today?", "peepol po today?", "pepol po na?", "pipol po na?", "peepol po na?", "pepol po na ba?", "pipol po na ba?", "peepol po na ba?", "pipol poh po?", "peepol poh po?", "pipol poh po!", "peepol poh po!", "pipol poh po!!", "peepol poh po!!", "pipol poh po!!!", "peepol poh po!!!"
],
      "responses": ["The PureFlow bussiness started in year 2012. The owner of this humble business is Mrs. Emelda along with her husband and her family. Mrs. Emelda or we call her as Nanay Mel is the head of the business who keeps the continous growth and continue expanding mkre branches."]
    },


    {
      "tag": "locations",
      "patterns": 
      ["location", "Location", "LOCATION", "loc", "lokasyon", "lokation", "location?", "Location?", "LOCATION?", "location po?", "Location po?", "location poh?", "location poh po?", "location po ba?", "location ba?", "location ba po?", "location po ba?", "location po ba?", "location po?", "location po!!", "location po!!!", "location poh po!", "location poh po!!", "location poh po!!!", "location poh po!!!!", "location po na?", "location po ngaun?", "location po ngayon?", "location po today?", "location today?", "location now?", "location po now?", "location po today?", "location po ngayon ba?", "location po ngaun ba?", "location po na ba?", "location po poh ba?", "location po poh?", "loc po?", "loc poh?", "loc po ba?", "loc ba?", "loc ba po?", "loc po ba?", "loc po ba?", "loc po!", "loc po!!", "loc poh po!", "loc poh po!!", "loc poh po!!!", "loc poh po!!!!", "lokasyon po?", "lokasyon poh?", "lokasyon po ba?", "lokasyon ba?", "lokasyon ba po?", "lokasyon po ba?", "lokasyon po ngaun?", "lokasyon po ngayon?", "lokasyon po today?", "lokasyon po ngaun ba?", "lokasyon po ngayon ba?", "lokasyon po na?", "lokasyon po na ba?", "lokasyon po poh ba?", "lokasyon po poh?", "lokasyon poh po?", "lokasyon poh po!", "lokasyon poh po!!", "lokasyon poh po!!!",
    "where", "Where", "WHERE", "wher", "wher?", "where?", "where po?", "Where po?", "WHERE PO?", "where poh?", "where poh po?", "where po ba?", "where ba?", "where ba po?", "where po ba?", "where po ba?", "where po today?", "where po now?", "where po ngaun?", "where po ngayon?", "where po ngaun ba?", "where po ngayon ba?", "where po na?", "where po na ba?", "where na po?", "where na?", "where ka?", "where kayo?", "where kayo po?", "where po kayo?", "where po kayo?", "where po kayo ba?", "where po kayo ngayon?", "where po kayo ngaun?", "where po kayo today?", "where po kayo na?", "where po kayo ngaun ba?", "where po kayo ngayon ba?", "where po kayo poh?", "where po kayo poh?", "where po kayo poh?", "where kayo poh po?", "where kayo poh po!!", "where kayo poh po!!!", "where kayo poh po!!!!", "where po kayo poh po!!!", "where po kayo poh po!!!!", "where po kayo poh po!!!!!", "where kayo poh po!!!!!", "where po kayo poh po!!!!!", "where kayo poh po!!!!", "where kayo poh po!!!", "where kayo poh po!!", "where kayo poh po!", "where kayo poh po?", "where po kayo poh po?", "where po kayo poh po!", "where po kayo poh po!!", "where po kayo poh po!!!",
    "saan", "Saan", "SAAN", "saan?", "Saan?", "SAAN?", "san", "San", "SAN", "san?", "San?", "SAN?", "saan po?", "Saan po?", "saan poh?", "saan poh po?", "saan po ba?", "saan ba?", "saan ba po?", "saan po ba?", "saan po ba?", "saan po ngaun?", "saan po ngayon?", "saan po today?", "saan today?", "saan now?", "saan po ngaun ba?", "saan po ngayon ba?", "saan po na?", "saan po na ba?", "saan na po?", "saan na?", "saan po poh ba?", "saan po poh?", "saan poh po?", "saan poh po!", "saan poh po!!", "saan poh po!!!", "saan poh po!!!!", "saan poh po!!!!!", "saan poh po!!", "saan poh po!!!", "saan poh po!!!", "saan po poh po!!!", "saan po poh po!!!!", "saan po poh po!!!!!", "saan kayo?", "Saan kayo?", "SAAN KAYO?", "saan kayo po?", "Saan kayo po?", "SAAN KAYO PO?", "saan kayo poh?", "saan kayo po ba?", "saan kayo ba?", "saan kayo ba po?", "saan kayo po ba?", "saan kayo po ngayon?", "saan kayo po ngaun?", "saan kayo po today?", "saan kayo po ngaun ba?", "saan kayo po ngayon ba?", "saan kayo po na?", "saan kayo po na ba?", "saan kayo po poh ba?", "saan kayo po poh?", "saan kayo poh po?", "saan kayo poh po!", "saan kayo poh po!!", "saan kayo poh po!!!", "saan kayo poh po!!!!", "saan kayo poh po!!!!!", "saan kayo poh po!!", "saan kayo poh po!!!", "saan kayo poh po!!!", "saan kayo poh po!!!!", "saan kayo poh po!!!!", "saan kayo poh po!!!!", "saan kayo poh po!!!!", "saan kayo poh po!!!!", "saan kayo poh po!!!!!", "saan kayo poh po!!!!!",
    "nakatayo", "Nakatayo", "NAKATAYO", "nktayo", "nktayo?", "nktayo po?", "nktayo poh?", "nktayo po ba?", "nktayo ba?", "nktayo ba po?", "nktayo po ba?", "nktayo po ngaun?", "nktayo po ngayon?", "nktayo po today?", "nktayo po na?", "nktayo po na ba?", "nktayo po poh?", "nktayo po poh ba?", "nktayo poh po?", "nktayo poh po!", "nktayo poh po!!", "nktayo poh po!!!", "nktayo poh po!!!!", "nktayo poh po!!!!!", "nktayo po poh po!!!", "nktayo po poh po!!!!", "nktayo po poh po!!!!!", "nakatayo po?", "Nakatayo po?", "NAKATAYO PO?", "nakatayo poh?", "nakatayo poh po?", "nakatayo po ba?", "nakatayo ba?", "nakatayo ba po?", "nakatayo po ba?", "nakatayo po ngaun?", "nakatayo po ngayon?", "nakatayo po today?", "nakatayo po na?", "nakatayo po na ba?", "nakatayo po poh?", "nakatayo po poh ba?", "nakatayo poh po?", "nakatayo poh po!", "nakatayo poh po!!", "nakatayo poh po!!!", "nakatayo poh po!!!!", "nakatayo poh po!!!!!", "nakatayo po poh po!!!", "nakatayo po poh po!!!!", "nakatayo po poh po!!!!!",
    "saan location", "Saan location", "SAAN LOCATION", "saan loc", "saan loc?", "saan location?", "saan location po?", "saan location po ba?", "saan location ba?", "saan location ba po?", "saan location po ngaun?", "saan location po ngayon?", "saan location po today?", "saan location po ngaun ba?", "saan location po ngayon ba?", "saan location po na?", "saan location po na ba?", "saan location po poh?", "saan location po poh ba?", "saan location poh po?", "saan location poh po!", "saan location poh po!!", "saan location poh po!!!", "saan location poh po!!!!", "saan location poh po!!!!!", "saan location poh po!!", "saan location poh po!!!", "saan location poh po!!!", "saan location poh po!!!!", "saan location poh po!!!!!",
    "where location", "Where location", "WHERE LOCATION", "wher loc", "where loc?", "where location?", "where location po?", "where location po ba?", "where location ba?", "where location ba po?", "where location po ngaun?", "where location po ngayon?", "where location po today?", "where location po ngaun ba?", "where location po ngayon ba?", "where location po na?", "where location po na ba?", "where location po poh?", "where location po poh ba?", "where location poh po?", "where location poh po!", "where location poh po!!", "where location poh po!!!", "where location poh po!!!!", "where location poh po!!!!!"
],
      "responses": ["We have two branch, Calero branch located ,in Calero Calapan City Oriental Mindoro and our main branch in Lazareto Calapan City Oriental Mindoro"]
    },

    {
      "tag": "direct_message",
      "patterns": 
      ["chat with agent", "Chat with agent", "CHAT WITH AGENT", "chat w agent", "chat wit agent", "chat with agen", "chaat with agent", "chatt with agent", "chat with agnt", "chat wth agent", "chat wid agent",  
"need assistance", "Need assistance", "NEED ASSISTANCE", "need asistans", "need asistance", "need assist", "need asist", "neeed assistance", "need assitance", "nEd assistance",  
"talk to human", "Talk to human", "TALK TO HUMAN", "talk 2 human", "tok to human", "tlk to human", "tAlk To HumAn", "talk too human", "talk wid human", "talk with human",  
"message support", "Message support", "MESSAGE SUPPORT", "msg support", "mesage support", "message suport", "mssg support", "msg supprt", "message sup", "message supp",  
"kausap", "Kausap", "KAUSAP", "ka usap", "kawusap", "kausapp", "kausappo", "kausapo", "kausap po", "kausapo!", "kausap poh", "kausap poh!",  
"need ka talk", "Need ka talk", "NEED KA TALK", "need katalk", "nid ka talk", "nid katalk", "need k talk", "need ka tok", "need ka tlk", "needka tlk",  
"talk with manager", "Talk with manager", "TALK WITH MANAGER", "talk wid manager", "talk w manager", "talk to manager", "tok with manager", "tlk with manager", "talk with maneger", "talk wth manager",  
"call admin", "Call admin", "CALL ADMIN", "kol admin", "cal admin", "call addmin", "cull admin", "call admn", "Call the admin", "call the admin",  
"call manager", "Call manager", "CALL MANAGER", "kol manager", "cal manager", "call maneger", "call the manager", "cull manager", "Call Mngr", "Call mnger",  
"speak with", "Speak with", "SPEAK WITH", "spik with", "speek with", "speak wid", "spik wit", "speek wit", "spk with", "speak to", "spik to", "speek to",  
"person with authority", "Person with authority", "PERSON WITH AUTHORITY", "person wid authority", "person with authoriti", "persn with author", "persn wid authority", "prs with authority", "person in charge", "person of authority",  
"nakatataas", "Nakatataas", "NAKATATAAS", "nakatatas", "nakakataas", "nakataas", "nakaataas", "nakatass", "nkatataas", "nakataaas"
],
      "responses": ["Please chat with us in Live Chat for more personal inquiries or detailed assistance. Our personnel can chat with your private concern regarding whatever purposes related to our business product and services. Thank you!!"]
    },

    
  ]
}

# --- SIMPLE MATCHING FUNCTION ---
def get_response(user_input):
    user_input = user_input.lower()
    for intent in intents["intents"]:
        for pattern in intent["patterns"]:
            if re.search(r"\b" + re.escape(pattern) + r"\b", user_input):
                return random.choice(intent["responses"])
    return "I'm sorry, I didn’t quite understand that. Could you rephrase?"

# --- API ENDPOINT ---
@app.route('/chat', methods=['POST'])
def chat():
    data = request.get_json()
    message = data.get('message', '')
    reply = get_response(message)
    return jsonify({'reply': reply})

# --- RUN SERVER ---
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
