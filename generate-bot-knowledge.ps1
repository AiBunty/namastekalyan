$ErrorActionPreference='Stop'
Set-Location 'd:\GITHUB Projects\Namaste Kalyan\namastekalyan'

$url='https://script.google.com/macros/s/AKfycbwb3W4gQNjbiYoFGdpKx4KyIhLA7xXpQqPvQC_v8gve7ck6_4M_TzHJzRscI3XfK40Q/exec?tab=AWGNK%20MENU&shape=grid'
$json=(Invoke-WebRequest -Uri $url -UseBasicParsing).Content | ConvertFrom-Json
if(-not $json.ok){ throw 'Menu API returned non-ok response.' }
$headers=@($json.headers)
$rows=@($json.rows)

function Norm([string]$h){
  if([string]::IsNullOrWhiteSpace($h)){ return '' }
  $k=$h.Trim().ToLower()
  switch($k){
    'item name' { 'Item Name'; break }
    'description' { 'Description'; break }
    'category' { 'Category'; break }
    'image url' { 'Image URL'; break }
    'jain' { 'Jain'; break }
    'chef special' { 'Chef Special'; break }
    "chef's special" { 'Chef Special'; break }
    'spice level' { 'Spice Level'; break }
    'veg' { 'Veg'; break }
    'chicken' { 'Chicken'; break }
    'mutton' { 'Mutton'; break }
    'basa' { 'Basa'; break }
    'prawn' { 'Prawns'; break }
    'prawans' { 'Prawns'; break }
    'prawns' { 'Prawns'; break }
    'surmai' { 'Surmai'; break }
    'pomfret' { 'Pomfret'; break }
    'crab' { 'Crab'; break }
    'egg' { 'Egg'; break }
    'half' { 'Half'; break }
    'full' { 'Full'; break }
    'plain' { 'Plain'; break }
    'butter' { 'Butter'; break }
    'medium' { 'Medium'; break }
    'large' { 'Large'; break }
    default { $h.Trim() }
  }
}

function Get-CategorySlug([string]$category) {
  if([string]::IsNullOrWhiteSpace($category)) { return 'other' }
  return (($category -replace '[^a-zA-Z0-9]', '-').ToLower())
}

function Add-ChatExample([System.Collections.Generic.List[string]]$lines, [ref]$num, [string]$userText, [string]$botText, [string]$botExtra = '') {
  $lines.Add(('{0}. User: {1}' -f $num.Value, $userText))
  $lines.Add(('   Bot: {0}' -f $botText))
  if(-not [string]::IsNullOrWhiteSpace($botExtra)) {
    $lines.Add(('   Bot: {0}' -f $botExtra))
  }
  $lines.Add('')
  $num.Value++
}

$canonHeaders=@($headers | ForEach-Object { Norm "$_" })
$idx=@{}
for($i=0; $i -lt $canonHeaders.Count; $i++){
  if($canonHeaders[$i]){ $idx[$canonHeaders[$i]]=$i }
}

$priceCols=@('Veg','Jain','Chicken','Mutton','Basa','Prawns','Surmai','Pomfret','Crab','Egg','Half','Full','Plain','Butter','Medium','Large')

$items=@()
foreach($r in $rows){
  if(-not $r){ continue }

  $cat='Other'
  if($idx.ContainsKey('Category')){ $cat=[string]$r[$idx['Category']] }

  $name=''
  if($idx.ContainsKey('Item Name')){ $name=[string]$r[$idx['Item Name']] }
  if([string]::IsNullOrWhiteSpace($name)){ continue }

  $desc=''
  if($idx.ContainsKey('Description')){ $desc=[string]$r[$idx['Description']] }

  $priceParts=@()
  foreach($c in $priceCols){
    if($idx.ContainsKey($c)){
      $v=$r[$idx[$c]]
      if($null -ne $v -and -not [string]::IsNullOrWhiteSpace([string]$v)){
        $priceParts += ('{0}: {1}' -f $c,([string]$v).Trim())
      }
    }
  }

  $isCrabName = $name.ToLower().Contains('crab')
  $hasCrabPrice = $false
  if($idx.ContainsKey('Crab')){
    $cv=$r[$idx['Crab']]
    if($null -ne $cv -and -not [string]::IsNullOrWhiteSpace([string]$cv)){ $hasCrabPrice=$true }
  }

  $items += [pscustomobject]@{
    Category = if([string]::IsNullOrWhiteSpace($cat)){'Other'}else{$cat.Trim()}
    Name = $name.Trim()
    Description = if([string]::IsNullOrWhiteSpace($desc)){'Description not available.'}else{$desc.Trim()}
    Prices = if($priceParts.Count){$priceParts -join ' | '}else{'Price not listed; please ask waiter.'}
    CrabNote = if($isCrabName -and -not $hasCrabPrice){'Crab price is not fixed. Please ask waiter for current price.'}else{''}
  }
}

$grouped=$items | Group-Object Category | Sort-Object Name
$generated=(Get-Date).ToString('yyyy-MM-dd HH:mm')

$foodMenuUrl = 'https://namastekalyan.asianwokandgrill.in/menu.html'
$cocktailMenuUrl = 'https://namastekalyan.asianwokandgrill.in/cocktail.html'
$reviewUrl = 'https://search.google.com/local/writereview?placeid=ChIJIdXER6mX5zsReLG1LBIMRqE&source=search&review=1'
$reserveUrl = 'https://admin.aibunty.com/u2/82800/reservation-for-awg'
$swiggyUrl = 'https://www.swiggy.com/restaurants/namaste-kalyan-by-asian-wok-and-grill-kalyan-mumbai-1000913/dineout'
$zomatoOrderUrl = 'https://www.zomato.com/mumbai/namaste-kalyan-by-asian-wok-and-grill-kalyan-thane'

$basicEn = @"
Namaste Kalyan by AWG - Basic Information (English)
Generated: $generated

Website: https://namastekalyan.asianwokandgrill.in/
Food Menu: $foodMenuUrl
Cocktail Menu: $cocktailMenuUrl
Google Review: $reviewUrl

Location Address:
RockMount Residency, 4th, Khadakpada Circle, Kalyan, Maharashtra 421301

Contact:
Phone / Call: +91 93715 19999
WhatsApp: https://wa.me/919371519999
Email: namastekalyan09@gmail.com

Order / Reservation Links:
Book Table / Event (CRM Calendar): $reserveUrl
Order on Swiggy: $swiggyUrl
Order on Zomato: $zomatoOrderUrl

Bot Rule:
Provide complete restaurant info, menu links, and contact actions first.
If crab item price is unavailable, always respond: Crab price is not fixed. Please ask waiter for current price.
"@

$basicHi = @"
Namaste Kalyan by AWG - Basic Information (Hindi)
Generated: $generated

Website: https://namastekalyan.asianwokandgrill.in/
Food Menu: $foodMenuUrl
Cocktail Menu: $cocktailMenuUrl
Google Review: $reviewUrl

पता:
RockMount Residency, 4th, Khadakpada Circle, Kalyan, Maharashtra 421301

संपर्क:
फोन / कॉल: +91 93715 19999
WhatsApp: https://wa.me/919371519999
Email: namastekalyan09@gmail.com

ऑर्डर / रिजर्वेशन लिंक:
Table/Event Booking (CRM Calendar): $reserveUrl
Order on Swiggy: $swiggyUrl
Order on Zomato: $zomatoOrderUrl

Bot Rule:
यूज़र को पहले पूरी जानकारी दें: लोकेशन, मेन्यू लिंक, कॉल/व्हाट्सऐप/ईमेल.
अगर Crab आइटम का प्राइस न हो, हमेशा बोलें: Crab price is not fixed. Please ask waiter for current price.
"@

$basicMr = @"
Namaste Kalyan by AWG - Basic Information (Marathi)
Generated: $generated

Website: https://namastekalyan.asianwokandgrill.in/
Food Menu: $foodMenuUrl
Cocktail Menu: $cocktailMenuUrl
Google Review: $reviewUrl

पत्ता:
RockMount Residency, 4th, Khadakpada Circle, Kalyan, Maharashtra 421301

संपर्क:
फोन / कॉल: +91 93715 19999
WhatsApp: https://wa.me/919371519999
Email: namastekalyan09@gmail.com

ऑर्डर / रिझर्वेशन लिंक्स:
Table/Event Booking (CRM Calendar): $reserveUrl
Order on Swiggy: $swiggyUrl
Order on Zomato: $zomatoOrderUrl

Bot Rule:
युजरला आधी पूर्ण माहिती द्या: लोकेशन, मेन्यू लिंक, कॉल/व्हॉट्सअॅप/ईमेल.
Crab आयटमचा दर नसेल तर नेहमी सांगा: Crab price is not fixed. Please ask waiter for current price.
"@

$chatEnLines = New-Object 'System.Collections.Generic.List[string]'
$chatHiLines = New-Object 'System.Collections.Generic.List[string]'
$chatMrLines = New-Object 'System.Collections.Generic.List[string]'

$chatEnLines.Add('Namaste Kalyan Bot - Chat Conversations & Suggestions (English)')
$chatEnLines.Add(('Generated: {0}' -f $generated))
$chatEnLines.Add('Policy: Bot will not share prices in chat. Always redirect user to menu/category link for latest pricing.')
$chatEnLines.Add('')

$chatHiLines.Add('Namaste Kalyan Bot - Chat Conversations & Suggestions (Hindi)')
$chatHiLines.Add(('Generated: {0}' -f $generated))
$chatHiLines.Add('Policy: Bot chat में price नहीं बताएगा। हमेशा latest pricing के लिए menu/category link देगा।')
$chatHiLines.Add('')

$chatMrLines.Add('Namaste Kalyan Bot - Chat Conversations & Suggestions (Marathi)')
$chatMrLines.Add(('Generated: {0}' -f $generated))
$chatMrLines.Add('Policy: Bot chat मध्ये किंमत सांगणार नाही. नेहमी latest pricing साठी menu/category link देईल.')
$chatMrLines.Add('')

$nEn = 1; $nHi = 1; $nMr = 1

Add-ChatExample $chatEnLines ([ref]$nEn) 'Hi' 'Welcome to Namaste Kalyan by AWG. I can help with menu links, location, reservation, and contact details.' 'We serve Veg, Non-Veg, and Jain menu options.'
Add-ChatExample $chatEnLines ([ref]$nEn) 'Hello' 'Hello. Please tell me what you need: food menu, cocktail menu, category link, reservation, or contact details.' 'We serve Veg, Non-Veg, and Jain menu options.'
Add-ChatExample $chatEnLines ([ref]$nEn) 'Menu' ('Here is the food menu: {0}' -f $foodMenuUrl) 'For latest pricing, please refer the menu directly. We serve Veg, Non-Veg, and Jain menu options.'
Add-ChatExample $chatEnLines ([ref]$nEn) 'Do you serve Veg, Non-Veg and Jain?' 'Yes. We serve Veg, Non-Veg, and Jain menu options.' ('Please explore categories here: {0}' -f $foodMenuUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'What is the price of Paneer Tikka?' 'I do not provide pricing in chat.' ('Please check the live menu for latest prices: {0}' -f $foodMenuUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'Where are you located?' 'RockMount Residency, 4th, Khadakpada Circle, Kalyan, Maharashtra 421301.' 'Call +91 93715 19999 or WhatsApp https://wa.me/919371519999'
Add-ChatExample $chatEnLines ([ref]$nEn) 'Share cocktail menu' ('Here is the cocktail menu: {0}' -f $cocktailMenuUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'Share contact details' 'Call: +91 93715 19999 | WhatsApp: https://wa.me/919371519999 | Email: namastekalyan09@gmail.com'
Add-ChatExample $chatEnLines ([ref]$nEn) 'I want to reserve a table' ('Book here (CRM calendar): {0}' -f $reserveUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'I want event booking' ('For any event booking, use CRM calendar: {0}' -f $reserveUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'Order online' ('Swiggy: {0}' -f $swiggyUrl) ('Zomato: {0}' -f $zomatoOrderUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'I want to write review' ('Google review link: {0}' -f $reviewUrl)
Add-ChatExample $chatEnLines ([ref]$nEn) 'What is crab price?' 'Crab price is not fixed. Please ask waiter for current price.'
Add-ChatExample $chatEnLines ([ref]$nEn) 'Show veg category link' ('Please open the menu and choose category: {0}' -f $foodMenuUrl)

Add-ChatExample $chatHiLines ([ref]$nHi) 'Hi' 'Namaste Kalyan by AWG में आपका स्वागत है। मैं मेन्यू लिंक, लोकेशन, रिजर्वेशन और संपर्क में मदद कर सकता हूं।' 'हम Veg, Non-Veg और Jain मेन्यू सर्व करते हैं।'
Add-ChatExample $chatHiLines ([ref]$nHi) 'Hello' 'नमस्ते। कृपया बताइए आपको क्या चाहिए: food menu, cocktail menu, category link, reservation या contact details.' 'हम Veg, Non-Veg और Jain मेन्यू सर्व करते हैं।'
Add-ChatExample $chatHiLines ([ref]$nHi) 'Menu' ('यह रहा food menu: {0}' -f $foodMenuUrl) 'Latest pricing के लिए कृपया मेन्यू देखें। हम Veg, Non-Veg और Jain मेन्यू सर्व करते हैं।'
Add-ChatExample $chatHiLines ([ref]$nHi) 'क्या आप Veg, Non-Veg और Jain सर्व करते हैं?' 'हाँ। हम Veg, Non-Veg और Jain मेन्यू सर्व करते हैं।' ('Category-wise menu यहां देखें: {0}' -f $foodMenuUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Paneer Tikka का price?' 'मैं chat में price नहीं बताता।' ('Latest price के लिए यह live menu देखें: {0}' -f $foodMenuUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Location बताओ' 'हमारा पता: RockMount Residency, 4th, Khadakpada Circle, Kalyan, Maharashtra 421301.' 'Call +91 93715 19999 या WhatsApp https://wa.me/919371519999'
Add-ChatExample $chatHiLines ([ref]$nHi) 'Cocktail menu भेजो' ('यह रहा cocktail menu: {0}' -f $cocktailMenuUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Contact details दो' 'Call: +91 93715 19999 | WhatsApp: https://wa.me/919371519999 | Email: namastekalyan09@gmail.com'
Add-ChatExample $chatHiLines ([ref]$nHi) 'Table booking' ('यहाँ book करें (CRM calendar): {0}' -f $reserveUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Event booking' ('किसी भी event booking के लिए CRM calendar इस्तेमाल करें: {0}' -f $reserveUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Online order' ('Swiggy: {0}' -f $swiggyUrl) ('Zomato: {0}' -f $zomatoOrderUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Review link दो' ('Google review link: {0}' -f $reviewUrl)
Add-ChatExample $chatHiLines ([ref]$nHi) 'Crab price?' 'Crab price is not fixed. Please ask waiter for current price.'
Add-ChatExample $chatHiLines ([ref]$nHi) 'Veg category link दो' ('Menu open करें: {0}' -f $foodMenuUrl)

Add-ChatExample $chatMrLines ([ref]$nMr) 'Hi' 'Namaste Kalyan by AWG मध्ये स्वागत आहे. मी menu link, location, reservation आणि contact मध्ये मदत करू शकतो.' 'आमच्याकडे Veg, Non-Veg आणि Jain menu उपलब्ध आहे.'
Add-ChatExample $chatMrLines ([ref]$nMr) 'Hello' 'नमस्कार. कृपया सांगा: food menu, cocktail menu, category link, reservation किंवा contact details.' 'आमच्याकडे Veg, Non-Veg आणि Jain menu उपलब्ध आहे.'
Add-ChatExample $chatMrLines ([ref]$nMr) 'Menu' ('हा food menu link: {0}' -f $foodMenuUrl) 'Latest pricing साठी कृपया menu पहा. आमच्याकडे Veg, Non-Veg आणि Jain menu उपलब्ध आहे.'
Add-ChatExample $chatMrLines ([ref]$nMr) 'तुमच्याकडे Veg, Non-Veg आणि Jain आहे का?' 'होय. आमच्याकडे Veg, Non-Veg आणि Jain menu उपलब्ध आहे.' ('Category-wise menu येथे पहा: {0}' -f $foodMenuUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Paneer Tikka price काय?' 'मी chat मध्ये किंमत सांगत नाही.' ('Latest किंमत पाहण्यासाठी हा live menu link वापरा: {0}' -f $foodMenuUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Location सांगा' 'आमचा पत्ता: RockMount Residency, 4th, Khadakpada Circle, Kalyan, Maharashtra 421301.' 'Call +91 93715 19999 किंवा WhatsApp https://wa.me/919371519999'
Add-ChatExample $chatMrLines ([ref]$nMr) 'Cocktail menu पाठवा' ('हा cocktail menu link: {0}' -f $cocktailMenuUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Contact details द्या' 'Call: +91 93715 19999 | WhatsApp: https://wa.me/919371519999 | Email: namastekalyan09@gmail.com'
Add-ChatExample $chatMrLines ([ref]$nMr) 'Table booking' ('येथे book करा (CRM calendar): {0}' -f $reserveUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Event booking' ('कोणत्याही event booking साठी CRM calendar वापरा: {0}' -f $reserveUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Online order' ('Swiggy: {0}' -f $swiggyUrl) ('Zomato: {0}' -f $zomatoOrderUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Review link द्या' ('Google review link: {0}' -f $reviewUrl)
Add-ChatExample $chatMrLines ([ref]$nMr) 'Crab price काय?' 'Crab price is not fixed. Please ask waiter for current price.'
Add-ChatExample $chatMrLines ([ref]$nMr) 'Veg category link द्या' ('Menu उघडा: {0}' -f $foodMenuUrl)

$categoryNames = @($grouped | ForEach-Object { $_.Name })
foreach($cat in $categoryNames) {
  $slug = Get-CategorySlug $cat
  $catUrl = '{0}#{1}' -f $foodMenuUrl, $slug

  Add-ChatExample $chatEnLines ([ref]$nEn) ('Show {0} menu' -f $cat) ('Open this direct category link: {0}' -f $catUrl) 'For latest pricing, please refer this category page.'
  Add-ChatExample $chatEnLines ([ref]$nEn) ('I want {0} items' -f $cat) ('Please check {0} here: {1}' -f $cat, $catUrl) 'I can help with recommendations, but pricing is available only on menu links.'
  Add-ChatExample $chatEnLines ([ref]$nEn) ('Give me {0} category link' -f $cat) ('Direct link: {0}' -f $catUrl)
  Add-ChatExample $chatEnLines ([ref]$nEn) ('Recommend from {0}' -f $cat) ('Open {0} category: {1}' -f $cat, $catUrl) 'Please refer this page for latest pricing.'
  Add-ChatExample $chatEnLines ([ref]$nEn) ('Is {0} available?' -f $cat) ('Please check live category menu here: {0}' -f $catUrl)

  Add-ChatExample $chatHiLines ([ref]$nHi) ('{0} menu दिखाओ' -f $cat) ('इस direct category link पर जाएं: {0}' -f $catUrl) 'Latest pricing के लिए इसी category page को देखें।'
  Add-ChatExample $chatHiLines ([ref]$nHi) ('मुझे {0} items चाहिए' -f $cat) ('{0} के लिए यह link खोलें: {1}' -f $cat, $catUrl) 'मैं suggestions दे सकता हूं, price के लिए menu link देखें।'
  Add-ChatExample $chatHiLines ([ref]$nHi) ('{0} category link दो' -f $cat) ('Direct link: {0}' -f $catUrl)
  Add-ChatExample $chatHiLines ([ref]$nHi) ('{0} में recommendation दो' -f $cat) ('{0} category खोलें: {1}' -f $cat, $catUrl) 'Latest price के लिए category page देखें।'
  Add-ChatExample $chatHiLines ([ref]$nHi) ('{0} available है?' -f $cat) ('Live category menu यहां देखें: {0}' -f $catUrl)

  Add-ChatExample $chatMrLines ([ref]$nMr) ('{0} menu दाखवा' -f $cat) ('हा direct category link उघडा: {0}' -f $catUrl) 'Latest pricing साठी याच category page ला भेट द्या.'
  Add-ChatExample $chatMrLines ([ref]$nMr) ('मला {0} items पाहिजेत' -f $cat) ('{0} साठी हा link उघडा: {1}' -f $cat, $catUrl) 'मी suggestions देऊ शकतो, किंमतीसाठी menu link पहा.'
  Add-ChatExample $chatMrLines ([ref]$nMr) ('{0} category link द्या' -f $cat) ('Direct link: {0}' -f $catUrl)
  Add-ChatExample $chatMrLines ([ref]$nMr) ('{0} मधून recommendation द्या' -f $cat) ('{0} category उघडा: {1}' -f $cat, $catUrl) 'Latest किंमत category page वर पहा.'
  Add-ChatExample $chatMrLines ([ref]$nMr) ('{0} available आहे का?' -f $cat) ('Live category menu येथे पहा: {0}' -f $catUrl)
}

$chatEnLines.Insert(3, ('Total Examples: {0}' -f ($nEn - 1)))
$chatHiLines.Insert(3, ('Total Examples: {0}' -f ($nHi - 1)))
$chatMrLines.Insert(3, ('Total Examples: {0}' -f ($nMr - 1)))

$chatEn = ($chatEnLines -join "`r`n")
$chatHi = ($chatHiLines -join "`r`n")
$chatMr = ($chatMrLines -join "`r`n")

$menuEn = "Namaste Kalyan - Menu Items Category Wise with Description (English)`r`nGenerated: $generated`r`n`r`nSource: Live AWGNK MENU sheet-backed API.`r`nRule: Crab price is not fixed. Please ask waiter for current price when not listed.`r`n"
$menuHi = "Namaste Kalyan - Menu Items Category Wise with Description (Hindi)`r`nGenerated: $generated`r`n`r`nSource: Live AWGNK MENU sheet-backed API.`r`nRule: Crab price is not fixed. Please ask waiter for current price when not listed.`r`n"
$menuMr = "Namaste Kalyan - Menu Items Category Wise with Description (Marathi)`r`nGenerated: $generated`r`n`r`nSource: Live AWGNK MENU sheet-backed API.`r`nRule: Crab price is not fixed. Please ask waiter for current price when not listed.`r`n"

foreach($g in $grouped){
  $menuEn += "`r`n==============================`r`nCategory: $($g.Name)`r`n==============================`r`n"
  $menuHi += "`r`n==============================`r`nश्रेणी: $($g.Name)`r`n==============================`r`n"
  $menuMr += "`r`n==============================`r`nविभाग: $($g.Name)`r`n==============================`r`n"

  foreach($it in $g.Group){
    $lineEn = "- Item: $($it.Name)`r`n  Description: $($it.Description)`r`n  Prices/Variants: $($it.Prices)"
    $lineHi = "- Item: $($it.Name)`r`n  विवरण: $($it.Description)`r`n  कीमत/वेरिएंट: $($it.Prices)"
    $lineMr = "- Item: $($it.Name)`r`n  वर्णन: $($it.Description)`r`n  किंमत/व्हेरिएंट: $($it.Prices)"

    if($it.CrabNote){
      $lineEn += "`r`n  Note: $($it.CrabNote)"
      $lineHi += "`r`n  नोट: Crab price is not fixed. Please ask waiter for current price."
      $lineMr += "`r`n  टीप: Crab price is not fixed. Please ask waiter for current price."
    }

    $menuEn += $lineEn + "`r`n"
    $menuHi += $lineHi + "`r`n"
    $menuMr += $lineMr + "`r`n"
  }
}

Set-Content -Path 'restaurant-basic-info-en.txt' -Value $basicEn -Encoding UTF8
Set-Content -Path 'restaurant-basic-info-hi.txt' -Value $basicHi -Encoding UTF8
Set-Content -Path 'restaurant-basic-info-mr.txt' -Value $basicMr -Encoding UTF8
Set-Content -Path 'restaurant-chat-suggestions-en.txt' -Value $chatEn -Encoding UTF8
Set-Content -Path 'restaurant-chat-suggestions-hi.txt' -Value $chatHi -Encoding UTF8
Set-Content -Path 'restaurant-chat-suggestions-mr.txt' -Value $chatMr -Encoding UTF8
Set-Content -Path 'restaurant-menu-category-wise-en.txt' -Value $menuEn -Encoding UTF8
Set-Content -Path 'restaurant-menu-category-wise-hi.txt' -Value $menuHi -Encoding UTF8
Set-Content -Path 'restaurant-menu-category-wise-mr.txt' -Value $menuMr -Encoding UTF8

Write-Output ("Created 9 files. Categories={0} Items={1} ExamplesEN={2} ExamplesHI={3} ExamplesMR={4}" -f $grouped.Count, $items.Count, ($nEn - 1), ($nHi - 1), ($nMr - 1))
