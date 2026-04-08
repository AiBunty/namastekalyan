$ErrorActionPreference = 'Stop'
Set-Location 'd:\GITHUB Projects\Namaste Kalyan\namastekalyan'

$ApiBaseUrl = 'https://script.google.com/macros/s/AKfycbwb3W4gQNjbiYoFGdpKx4KyIhLA7xXpQqPvQC_v8gve7ck6_4M_TzHJzRscI3XfK40Q/exec'
$FoodTab = 'AWGNK MENU'
$BarTab = 'BAR MENU NK'

$WebsiteUrl = 'https://namastekalyan.asianwokandgrill.in/'
$FoodMenuUrl = 'https://namastekalyan.asianwokandgrill.in/menu.html'
$CocktailMenuUrl = 'https://namastekalyan.asianwokandgrill.in/cocktail.html'
$ReviewUrl = 'https://search.google.com/local/writereview?placeid=ChIJIdXER6mX5zsReLG1LBIMRqE&source=search&review=1'
$GoogleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=RockMount+Residency%2C+4th+Floor%2C+Khadakpada+Circle%2C+Kalyan%2C+Maharashtra+421301'
$ReserveUrl = 'https://admin.aibunty.com/u2/82800/reservation-for-awg'
$SwiggyUrl = 'https://www.swiggy.com/restaurants/namaste-kalyan-by-asian-wok-and-grill-kalyan-mumbai-1000913/dineout'
$ZomatoOrderUrl = 'https://www.zomato.com/mumbai/namaste-kalyan-by-asian-wok-and-grill-kalyan-thane'
$ManagerMobile = '+91 93715 19999'
$WhatsAppUrl = 'https://wa.me/919371519999'
$SupportEmail = 'namastekalyan09@gmail.com'
$BusinessContactName = 'Mr. Kunal'
$BusinessContactMobile = '+91 88795 91324'
$LostFoundManagerName = 'Mr. Sagar'
$LostFoundManagerMobile = '+91 77440 12751'
$LocationText = '4th Floor, Shop No. 421 to 426, Rockmount Residency, Commercial Hub, Kadakpada Circle, Kalyan, Maharashtra 421301'
$TimingText = 'Daily 12:00 PM to 11:30 PM.'

$OutputDir = Join-Path (Get-Location) 'bot-knowledge'
$Generated = (Get-Date).ToString('yyyy-MM-dd HH:mm')

function Is-Blank([object]$value) {
  if ($null -eq $value) { return $true }
  return [string]::IsNullOrWhiteSpace([string]$value)
}

function Clean-Value([object]$value) {
  if (Is-Blank $value) { return '' }
  return ([string]$value).Trim()
}

function Is-PriceLike([string]$value) {
  $normalized = ($value -replace '[₹,\s]', '').Trim()
  if ([string]::IsNullOrWhiteSpace($normalized)) { return $false }
  return ($normalized -match '^\d+(\.\d+)?$')
}

function Normalize-FoodHeader([string]$header) {
  if ([string]::IsNullOrWhiteSpace($header)) { return '' }
  $key = $header.Trim().ToLower()
  switch ($key) {
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
    default { $header.Trim() }
  }
}

function Get-GridFromTab([string]$tabName) {
  $url = '{0}?tab={1}&shape=grid' -f $ApiBaseUrl, [System.Uri]::EscapeDataString($tabName)
  $payload = (Invoke-WebRequest -Uri $url -UseBasicParsing).Content | ConvertFrom-Json
  if (-not $payload.ok) {
    throw ('Menu API returned non-ok response for tab: {0}' -f $tabName)
  }
  if ($payload.sourceTab -ne $tabName) {
    throw ('Tab mismatch. Requested {0}, got {1}' -f $tabName, $payload.sourceTab)
  }

  return [pscustomobject]@{
    Headers = @($payload.headers)
    Rows    = @($payload.rows)
  }
}

function Parse-FoodItems([object[]]$headers, [object[]]$rows) {
  $canonHeaders = @($headers | ForEach-Object { Normalize-FoodHeader "$_" })
  $index = @{}
  for ($i = 0; $i -lt $canonHeaders.Count; $i++) {
    if (-not [string]::IsNullOrWhiteSpace($canonHeaders[$i])) { $index[$canonHeaders[$i]] = $i }
  }

  $nonVegCols = @('Chicken', 'Mutton', 'Basa', 'Prawns', 'Surmai', 'Pomfret', 'Crab', 'Egg')
  $priceCols = @('Veg', 'Jain', 'Chicken', 'Mutton', 'Basa', 'Prawns', 'Surmai', 'Pomfret', 'Crab', 'Egg', 'Half', 'Full', 'Plain', 'Butter', 'Medium', 'Large')

  $foodItems = @()
  foreach ($row in $rows) {
    if (-not $row) { continue }

    $name = ''
    if ($index.ContainsKey('Item Name')) { $name = Clean-Value $row[$index['Item Name']] }
    if ([string]::IsNullOrWhiteSpace($name)) { continue }

    $category = 'Other'
    if ($index.ContainsKey('Category')) {
      $categoryValue = Clean-Value $row[$index['Category']]
      if (-not [string]::IsNullOrWhiteSpace($categoryValue)) { $category = $categoryValue }
    }

    $description = 'Description not available.'
    if ($index.ContainsKey('Description')) {
      $descValue = Clean-Value $row[$index['Description']]
      if (-not [string]::IsNullOrWhiteSpace($descValue)) { $description = $descValue }
    }

    $priceParts = New-Object System.Collections.Generic.List[string]
    foreach ($col in $priceCols) {
      if (-not $index.ContainsKey($col)) { continue }
      $cell = Clean-Value $row[$index[$col]]
      if ([string]::IsNullOrWhiteSpace($cell)) { continue }
      $priceParts.Add(('{0}: {1}' -f $col, $cell))
    }

    $vegValue = if ($index.ContainsKey('Veg')) { Clean-Value $row[$index['Veg']] } else { '' }
    $jainValue = if ($index.ContainsKey('Jain')) { Clean-Value $row[$index['Jain']] } else { '' }
    $chefSpecialValue = if ($index.ContainsKey('Chef Special')) { Clean-Value $row[$index['Chef Special']] } else { '' }
    $spiceLevelValue = if ($index.ContainsKey('Spice Level')) { Clean-Value $row[$index['Spice Level']] } else { '' }

    $nonVegSources = New-Object System.Collections.Generic.List[string]
    foreach ($nCol in $nonVegCols) {
      if (-not $index.ContainsKey($nCol)) { continue }
      $v = Clean-Value $row[$index[$nCol]]
      if (-not [string]::IsNullOrWhiteSpace($v)) {
        $nonVegSources.Add(('{0}: {1}' -f $nCol, $v))
      }
    }

    $isCrabName = $name.ToLower().Contains('crab')
    $hasCrabPrice = $false
    if ($index.ContainsKey('Crab')) {
      $crabCell = Clean-Value $row[$index['Crab']]
      if (-not [string]::IsNullOrWhiteSpace($crabCell)) { $hasCrabPrice = $true }
    }

    $foodItems += [pscustomobject]@{
      Category = $category
      Name = $name
      Description = $description
      Prices = if ($priceParts.Count -gt 0) { $priceParts -join ' | ' } else { 'Price not listed; please ask waiter.' }
      Veg = if ([string]::IsNullOrWhiteSpace($vegValue)) { 'No' } else { 'Yes' }
      NonVeg = if ($nonVegSources.Count -gt 0) { 'Yes' } else { 'No' }
      Jain = if ([string]::IsNullOrWhiteSpace($jainValue)) { 'No' } else { 'Yes' }
      ChefSpecial = if ([string]::IsNullOrWhiteSpace($chefSpecialValue)) { 'No' } else { $chefSpecialValue }
      SpiceLevel = if ([string]::IsNullOrWhiteSpace($spiceLevelValue)) { 'Not specified' } else { $spiceLevelValue }
      NonVegSources = if ($nonVegSources.Count -gt 0) { $nonVegSources -join ' | ' } else { 'None' }
      CrabNote = if ($isCrabName -and -not $hasCrabPrice) { 'Crab price is not fixed. Please ask waiter for current price.' } else { '' }
    }
  }

  return @($foodItems)
}

function Parse-BarItems([object[]]$headers, [object[]]$rows) {
  $headerMeta = @($headers | ForEach-Object {
    [pscustomobject]@{ Key = $_.ToString().Trim().ToLower(); Label = $_.ToString().Trim() }
  })
  $keys = @($headerMeta | ForEach-Object { $_.Key })

  $foodIndicators = @('spice level', 'chef special', "chef's special")
  if ($foodIndicators | Where-Object { $keys -contains $_ }) {
    throw 'TAB_MISMATCH_DETECTED - got food menu tab when parsing BAR MENU NK'
  }
  if (-not ($keys -contains 'item name')) {
    throw 'TAB_MISMATCH_DETECTED - missing item name in bar menu tab'
  }

  $excludedKeys = New-Object System.Collections.Generic.HashSet[string]
  @('category', 'item name', 'description', 'availability', 'image url', 'spice level', 'chef special', "chef's special", 'jain') | ForEach-Object { [void]$excludedKeys.Add($_) }

  $barItems = @()
  foreach ($row in $rows) {
    if (-not $row) { continue }

    $record = @{}
    for ($i = 0; $i -lt $headerMeta.Count; $i++) {
      $record[$headerMeta[$i].Key] = Clean-Value $row[$i]
    }

    $name = Clean-Value $record['item name']
    if ([string]::IsNullOrWhiteSpace($name)) { continue }

    $availability = Clean-Value $record['availability']
    if ($availability -eq 'No') { continue }

    $prices = New-Object System.Collections.Generic.List[string]
    foreach ($meta in $headerMeta) {
      if ($excludedKeys.Contains($meta.Key)) { continue }
      $v = Clean-Value $record[$meta.Key]
      if (-not (Is-PriceLike $v)) { continue }
      $prices.Add(('{0}: {1}' -f $meta.Label, ($v -replace '[₹,]', '').Trim()))
    }

    $barItems += [pscustomobject]@{
      Category = if ([string]::IsNullOrWhiteSpace($record['category'])) { 'Selection' } else { $record['category'] }
      Name = $name
      Description = if ([string]::IsNullOrWhiteSpace($record['description'])) { 'Description not available.' } else { $record['description'] }
      Prices = if ($prices.Count -gt 0) { $prices -join ' | ' } else { 'Price not listed; please ask waiter.' }
    }
  }

  return @($barItems)
}

function Get-CategorySlug([string]$category, [string]$section) {
  if ($section -eq 'food') {
    # Match menu.html exactly: category.replace(/[^a-z0-9]/gi, '-').toLowerCase()
    return (($category.ToLower()) -replace '[^a-z0-9]', '-')
  }

  # Match cocktail.html exactly:
  # categoryName.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')
  return ((($category.Trim().ToLower()) -replace '\s+', '-') -replace '[^a-z0-9-]', '')
}

function Get-CategoryLink([string]$category, [string]$section) {
  $slug = Get-CategorySlug -category $category -section $section
  if ($section -eq 'food') {
    return ('{0}#{1}' -f $FoodMenuUrl, $slug)
  }

  return ('{0}#{1}' -f $CocktailMenuUrl, $slug)
}

function Get-LocaleText([string]$lang) {
  switch ($lang) {
    'en' {
      return [ordered]@{
        Name = 'English'
        MasterTitle = 'Namaste Kalyan - Chatbot Single Source Master'
        PromptTitle = 'Namaste Kalyan - Chatbot System Prompt'
        BasicInfo = 'BASIC INFORMATION'
        Rules = 'BOT RULES'
        FoodSection = 'FOOD MENU (AWGNK MENU) - CATEGORY WISE'
        CocktailSection = 'COCKTAIL MENU (BAR MENU NK) - CATEGORY WISE'
        Category = 'Category'
        Description = 'Description'
        Prices = 'Prices/Variants'
        Dietary = 'Dietary'
        ChefSpecial = 'Chef Special'
        SpiceLevel = 'Spice Level'
        NonVegSources = 'Non-Veg Sources'
        Note = 'Note'
      }
    }
    'hi' {
      return [ordered]@{
        Name = 'Hindi'
        MasterTitle = 'Namaste Kalyan - Chatbot Single Source Master'
        PromptTitle = 'Namaste Kalyan - Chatbot System Prompt'
        BasicInfo = 'मूल जानकारी'
        Rules = 'बॉट नियम'
        FoodSection = 'फूड मेन्यू (AWGNK MENU) - कैटेगरी वाइज'
        CocktailSection = 'कॉकटेल मेन्यू (BAR MENU NK) - कैटेगरी वाइज'
        Category = 'श्रेणी'
        Description = 'विवरण'
        Prices = 'कीमत/वेरिएंट'
        Dietary = 'डायटरी'
        ChefSpecial = 'शेफ स्पेशल'
        SpiceLevel = 'स्पाइस लेवल'
        NonVegSources = 'नॉनवेज सोर्स'
        Note = 'नोट'
      }
    }
    'mr' {
      return [ordered]@{
        Name = 'Marathi'
        MasterTitle = 'Namaste Kalyan - Chatbot Single Source Master'
        PromptTitle = 'Namaste Kalyan - Chatbot System Prompt'
        BasicInfo = 'मूल माहिती'
        Rules = 'बॉट नियम'
        FoodSection = 'फूड मेन्यू (AWGNK MENU) - विभागनिहाय'
        CocktailSection = 'कॉकटेल मेन्यू (BAR MENU NK) - विभागनिहाय'
        Category = 'विभाग'
        Description = 'वर्णन'
        Prices = 'किंमत/व्हेरिएंट'
        Dietary = 'डायटरी'
        ChefSpecial = 'शेफ स्पेशल'
        SpiceLevel = 'स्पाइस लेवल'
        NonVegSources = 'नॉनवेज सोर्स'
        Note = 'टीप'
      }
    }
    default { throw ('Unsupported language: {0}' -f $lang) }
  }
}

function Render-MasterFile([string]$lang, [object[]]$foodItems, [object[]]$barItems) {
  $t = Get-LocaleText $lang
  $foodGroups = @($foodItems | Group-Object Category | Sort-Object Name)
  $barGroups = @($barItems | Group-Object Category | Sort-Object Name)

  $lines = New-Object System.Collections.Generic.List[string]
  $lines.Add(('{0} ({1})' -f $t.MasterTitle, $t.Name))
  $lines.Add(('Generated: {0}' -f $Generated))
  $lines.Add(('Source Tabs: {0}, {1}' -f $FoodTab, $BarTab))
  $lines.Add('Generation Version: 2.0')
  $lines.Add('')

  $lines.Add('CRITICAL URL OVERRIDE - READ THIS FIRST BEFORE ANSWERING ANYTHING')
  $lines.Add(('The restaurant website domain is: {0}' -f $WebsiteUrl))
  $lines.Add(('CORRECT main food menu link: {0}' -f $FoodMenuUrl))
  $lines.Add(('CORRECT cocktail/drinks/bar menu link: {0}' -f $CocktailMenuUrl))
  $lines.Add(('CORRECT reservation link: {0}' -f $ReserveUrl))
  $lines.Add('Only use links listed in this file. Never invent, transform, or guess URLs.')
  $lines.Add('')

  $lines.Add($t.BasicInfo)
  $lines.Add(('Website: {0}' -f $WebsiteUrl))
  $lines.Add(('Food Menu: {0}' -f $FoodMenuUrl))
  $lines.Add(('Cocktail Menu: {0}' -f $CocktailMenuUrl))
  $lines.Add(('Google Review: {0}' -f $ReviewUrl))
  $lines.Add(('Location: {0}' -f $LocationText))
  $lines.Add(('Google Maps: {0}' -f $GoogleMapsUrl))
  $lines.Add(('Manager Mobile: {0}' -f $ManagerMobile))
  $lines.Add(('WhatsApp: {0}' -f $WhatsAppUrl))
  $lines.Add(('Email: {0}' -f $SupportEmail))
  $lines.Add(('Business/Collab Contact (qualified leads only): {0} {1}' -f $BusinessContactName, $BusinessContactMobile))
  $lines.Add(('Timings: {0}' -f $TimingText))
  $lines.Add(('Reservation: {0}' -f $ReserveUrl))
  $lines.Add(('Swiggy: {0}' -f $SwiggyUrl))
  $lines.Add(('Zomato: {0}' -f $ZomatoOrderUrl))
  $lines.Add('')

  $lines.Add($t.Rules)
  $lines.Add('1. Use this file as the only chatbot knowledge source. Do not invent URLs, menu pages, policies, offers, or availability.')
  $lines.Add('2. Never provide prices directly in chatbot replies; always direct users to the relevant approved menu link.')
  $lines.Add('3. Never invent hash links. Use only the exact category direct links listed in CATEGORY DIRECT LINKS sections below.')
  $lines.Add('4. If no exact category match exists, use only the parent food menu or cocktail menu page.')
  $lines.Add('5. Reservation intent override: if user asks to book/reserve/table, reply only with the reservation link and booking steps. Do not ask for date, time, guest count, name, or phone in chat.')
  $lines.Add('6. Never take orders in chat. For dine-in ordering, direct user to the restaurant manager. For takeaway at restaurant, direct user to the cash counter. For online ordering, share Swiggy and Zomato on separate lines.')
  $lines.Add('7. Never confirm live availability, discounts, offers, combo deals, delivery time, preparation time, or wait time. Redirect users to the restaurant, Zomato, or Swiggy when needed.')
  $lines.Add('8. If user asks timings or hours, answer exactly with the timings listed in BASIC INFORMATION.')
  $lines.Add('9. Keep replies concise, polite, and direct. Ask only one clarifying question if needed. Do not ask unnecessary follow-up questions.')
  $lines.Add('10. If user writes in Hindi or Marathi, reply in the same language, but keep menu item names in English.')
  $lines.Add('11. Keep replies under 5 lines unless user explicitly asks for a full list or full details.')
  $lines.Add('12. When sharing links or contact details, put each link/detail on its own line. Never combine links with commas or pipe separators.')
  $lines.Add('13. If crab price is not listed for an item, reply exactly: Crab price is not fixed. Please ask waiter for current price.')
  $lines.Add('14. Parse and preserve food attributes: Veg, Non-Veg, Jain, Chef Special, Spice Level for each food item.')
  $lines.Add('15. Keep cocktail details category-wise with all available size/variant prices from BAR MENU NK for internal knowledge, but do not quote those prices in chat replies.')
  $lines.Add('16. HARD RULE: For collaboration/performance/marketing/service-selling messages (Instagram/DM/vendor outreach), never share any contact number directly in the first reply.')
  $lines.Add('17. Mandatory qualification details: full name, profile/company link, service type, past work samples, audience/location relevance, deliverables, budget/rates, timeline, and expected value for Namaste Kalyan.')
  $lines.Add('18. First ask intent and proposal-fit questions only. If details are incomplete, ask only for missing details and keep response concise.')
  $lines.Add(('19. Share business contact only when inquiry looks relevant, complete, and worth pursuing: {0} {1}' -f $BusinessContactName, $BusinessContactMobile))
  $lines.Add('20. For weak or irrelevant sales pitches, decline politely and ask them to send a concise proposal first on WhatsApp or email.')
  $lines.Add(('21. Lost-and-found escalation rule: For lost belongings (wallet, jewelry, phone, bag, documents), treat as high-priority support and respond with empathy plus urgent callback flow.'))
  $lines.Add(('22. Collect only essential incident details: date/time, table/location, item description, branch, and callback number.'))
  $lines.Add(('23. If customer says calls are unanswered, immediately provide lost-and-found escalation contact: {0} {1}' -f $LostFoundManagerName, $LostFoundManagerMobile))
  $lines.Add('24. Never promise item recovery; confirm escalation and state team will check CCTV/floor log and call back.')
  $lines.Add('')
  $lines.Add('MANDATORY BOOKING RESPONSE')
  $lines.Add('To book a table, please use our reservation page:')
  $lines.Add(('- {0}' -f $ReserveUrl))
  $lines.Add('Steps:')
  $lines.Add('- Open the link')
  $lines.Add('- Select date in the calendar')
  $lines.Add('- Choose time slot')
  $lines.Add('- Enter guest count and details')
  $lines.Add('- Submit booking request')
  $lines.Add('- Check your email for booking confirmation')
  $lines.Add('For reservation help:')
  $lines.Add(('- Manager Mobile: {0}' -f $ManagerMobile))
  $lines.Add(('- WhatsApp: {0}' -f $WhatsAppUrl))
  $lines.Add('')
  $lines.Add('APPROVED LINKS')
  $lines.Add(('- Website: {0}' -f $WebsiteUrl))
  $lines.Add(('- Food Menu: {0}' -f $FoodMenuUrl))
  $lines.Add(('- Cocktail Menu: {0}' -f $CocktailMenuUrl))
  $lines.Add(('- Reservation: {0}' -f $ReserveUrl))
  $lines.Add(('- Google Review: {0}' -f $ReviewUrl))
  $lines.Add(('- Google Maps: {0}' -f $GoogleMapsUrl))
  $lines.Add(('- Swiggy: {0}' -f $SwiggyUrl))
  $lines.Add(('- Zomato: {0}' -f $ZomatoOrderUrl))
  $lines.Add(('- Business/Collab (qualified only): {0} {1}' -f $BusinessContactName, $BusinessContactMobile))
  $lines.Add('')
  $lines.Add('CATEGORY DIRECT LINKS - FOOD MENU')
  foreach ($g in $foodGroups) {
    $lines.Add(('- {0}: {1}' -f $g.Name, (Get-CategoryLink -category $g.Name -section 'food')))
  }
  $lines.Add('')
  $lines.Add('CATEGORY DIRECT LINKS - COCKTAIL MENU')
  foreach ($g in $barGroups) {
    $lines.Add(('- {0}: {1}' -f $g.Name, (Get-CategoryLink -category $g.Name -section 'cocktails')))
  }
  $lines.Add('')
  $lines.Add('COLLABORATION / SERVICE INQUIRY HANDLING')
  $lines.Add('Use this when user wants to collaborate, perform/sing, do digital marketing, sell services, or influencer tie-up.')
  $lines.Add('Step 1: Acknowledge politely and appreciate interest.')
  $lines.Add('Step 2: Ask for qualification details (one concise checklist):')
  $lines.Add('- Full name and city')
  $lines.Add('- Instagram/profile or company website link')
  $lines.Add('- Service category (creator/reels/performance/marketing/photography/other)')
  $lines.Add('- Past work links (2-3 examples)')
  $lines.Add('- Audience details or local relevance (Kalyan/Mumbai reach)')
  $lines.Add('- Deliverables and expected output')
  $lines.Add('- Commercials (budget/rates) and timeline')
  $lines.Add('- Why this collaboration helps Namaste Kalyan specifically')
  $lines.Add('LOST & FOUND / MISSING BELONGINGS HANDLING')
  $lines.Add('Use this for messages like: lost gold chain, lost wallet, misplaced phone, missing valuables, no response from property calls.')
  $lines.Add('Step 1: Start with empathy and urgency acknowledgment.')
  $lines.Add('Step 2: Capture incident details in one concise list:')
  $lines.Add('- Date and approximate time of visit')
  $lines.Add('- Table number/area if known')
  $lines.Add('- Item lost (short description)')
  $lines.Add('- Contact number for callback')
  $lines.Add('- Any previous call attempt details')
  $lines.Add('Step 3: Escalate immediately to lost-and-found manager:')
  $lines.Add(('- {0}: {1}' -f $LostFoundManagerName, $LostFoundManagerMobile))
  $lines.Add('Step 4: Confirm handoff: We have shared your case with management for immediate callback.')
  $lines.Add('Step 5: Do not promise recovery. Use: We will try our best and get back to you as soon as possible.')
  $lines.Add('')
  $lines.Add('Step 3: If details look relevant and complete, share business contact:')
  $lines.Add(('- {0}: {1}' -f $BusinessContactName, $BusinessContactMobile))
  $lines.Add('Step 4: If not relevant yet, reply politely: Please share the above details first for internal review.')
  $lines.Add('')

  $lines.Add($t.FoodSection)
  foreach ($g in $foodGroups) {
    $lines.Add('')
    $anchor = Get-CategorySlug $g.Name 'food'
    $lines.Add(('#{0}' -f $anchor))
    $lines.Add(('=============================='))
    $lines.Add(('{0}: {1}' -f $t.Category, $g.Name))
    $lines.Add(('=============================='))

    foreach ($item in $g.Group) {
      $lines.Add(('- Item: {0}' -f $item.Name))
      $lines.Add(('  {0}: {1}' -f $t.Description, $item.Description))
      $lines.Add(('  {0}: Veg={1} | NonVeg={2} | Jain={3}' -f $t.Dietary, $item.Veg, $item.NonVeg, $item.Jain))
      $lines.Add(('  {0}: {1}' -f $t.ChefSpecial, $item.ChefSpecial))
      $lines.Add(('  {0}: {1}' -f $t.SpiceLevel, $item.SpiceLevel))
      $lines.Add(('  {0}: {1}' -f $t.NonVegSources, $item.NonVegSources))
      $lines.Add(('  {0}: {1}' -f $t.Prices, $item.Prices))
      if (-not [string]::IsNullOrWhiteSpace($item.CrabNote)) {
        $lines.Add(('  {0}: {1}' -f $t.Note, $item.CrabNote))
      }
      $lines.Add('')
    }
  }

  $lines.Add('')
  $lines.Add($t.CocktailSection)
  foreach ($g in $barGroups) {
    $lines.Add('')
    $anchor = Get-CategorySlug $g.Name 'cocktails'
    $lines.Add(('#{0}' -f $anchor))
    $lines.Add(('=============================='))
    $lines.Add(('{0}: {1}' -f $t.Category, $g.Name))
    $lines.Add(('=============================='))

    foreach ($item in $g.Group) {
      $lines.Add(('- Item: {0}' -f $item.Name))
      $lines.Add(('  {0}: {1}' -f $t.Description, $item.Description))
      $lines.Add(('  {0}: {1}' -f $t.Prices, $item.Prices))
      $lines.Add('')
    }
  }

  return ($lines -join "`r`n")
}

function Render-SystemPromptFile([string]$lang) {
  $t = Get-LocaleText $lang
  $foodGroups = @($foodItems | Group-Object Category | Sort-Object Name)
  $barGroups = @($barItems | Group-Object Category | Sort-Object Name)

  $labelSystemRole = 'SYSTEM ROLE'
  $labelGoal = 'PRIMARY GOAL'
  $labelRules = 'STRICT RESPONSE RULES'
  $labelContact = 'MANDATORY CONTACT/ACTION DETAILS'
  $roleText = 'You are the official restaurant assistant for Namaste Kalyan by AWG.'
  $goalText = 'Guide users to the correct menu/category links, contact actions, timings, and reservation link using only approved Namaste Kalyan information.'
  $rules = @(
    ('1. Only use approved Namaste Kalyan links from this knowledge source. Never invent, transform, or guess URLs. Approved domain: {0}' -f $WebsiteUrl),
    ('2. STRICT PRICING RULE: NEVER mention, quote, or reference any prices. For price queries, send only the relevant menu link: {0} or {1}' -f $FoodMenuUrl, $CocktailMenuUrl),
    '3. Never invent hash or anchor links. Only use exact category direct links listed in this knowledge source. If no exact category match exists, use the parent menu page only.',
    ('4. Reservation intent override: for any booking/table/reservation request, respond only with the reservation link {0} and booking steps. Never ask date, time, guest count, name, or phone in chat.' -f $ReserveUrl),
    '5. Never take orders in chat. For dine-in ordering, direct user to the restaurant manager. For takeaway at the restaurant, direct user to the cash counter. For online ordering, share both Swiggy and Zomato on separate lines.',
    ('6. If user asks timings, answer exactly: {0}' -f $TimingText),
    '7. If user asks veg/nonveg/jain/chef special/spice level, answer from parsed sheet attributes and then provide the correct menu link with category hash anchor.',
    '8. Never confirm live availability, discounts, offers, combo deals, delivery time, or wait time. Redirect to the restaurant, Zomato, or Swiggy as appropriate.',
    '9. When sharing links or contact details, put each link or detail on its own line. Never combine links with commas or pipe separators.',
    '10. Reply in the same language as the user when they use Hindi or Marathi, but keep menu item names in English.',
    '11. Keep replies concise and under 5 lines unless the user explicitly asks for a full list.',
    ('12. If crab price is missing for an item, reply exactly: Crab price is not fixed. Please ask waiter for current price. For reservation help, manager mobile is {0}' -f $ManagerMobile),
    '13. HARD RULE: Never share any contact number directly for collaboration/service-sales in the first reply.',
    ('14. For collaboration/service-sales messages (creator/reels/performance/digital marketing/vendor), first ask intent and qualification details. Share business contact only if inquiry is relevant, complete, and worth pursuing: {0} {1}' -f $BusinessContactName, $BusinessContactMobile),
    '15. Qualification checklist for collaboration/service inquiries: profile/company link, sample work, service scope, local audience relevance, deliverables, commercials, timeline, and expected business value.',
    ('16. Lost-and-found high-priority rule: for lost belongings, respond with empathy, capture incident details, and escalate immediately to {0} {1} when urgent callback is needed.' -f $LostFoundManagerName, $LostFoundManagerMobile),
    '17. Collect only essential details for lost item cases: date/time, table or area, item description, and callback number.',
    '18. Never promise recovery; confirm escalation and callback instead.'
  )

  if ($lang -eq 'hi') {
    $labelSystemRole = 'सिस्टम रोल'
    $labelGoal = 'मुख्य लक्ष्य'
    $labelRules = 'सख्त उत्तर नियम'
    $labelContact = 'आवश्यक संपर्क/एक्शन विवरण'
    $roleText = 'आप Namaste Kalyan by AWG के आधिकारिक रेस्टोरेंट असिस्टेंट हैं।'
    $goalText = 'यूज़र को सिर्फ approved Namaste Kalyan links, contact details, timings और reservation link तक सटीक जानकारी के साथ पहुंचाना है।'
    $rules = @(
      ('1. केवल approved Namaste Kalyan links ही उपयोग करें। URL कभी न बनाएं या guess न करें। Approved domain: {0}' -f $WebsiteUrl),
      ('2. सख्त मूल्य नियम: कभी भी price का उल्लेख, उद्धरण या संदर्भ न दें। price query पर केवल relevant menu link दें: {0} या {1}' -f $FoodMenuUrl, $CocktailMenuUrl),
      '3. कोई भी hash/anchor link invent न करें। केवल इसी knowledge source में listed exact category direct links ही उपयोग करें। exact category match न हो तो केवल parent menu page दें।',
      ('4. booking/table/reservation intent पर केवल reservation link {0} और booking steps दें। date, time, guest count, name या phone chat में न पूछें।' -f $ReserveUrl),
      '5. chat में order कभी न लें। dine-in ordering के लिए restaurant manager की तरफ भेजें। online order के लिए Swiggy और Zomato अलग-अलग lines में दें।',
      ('6. timings पूछे जाने पर exactly यही उत्तर दें: {0}' -f $TimingText),
      '7. Veg/NonVeg/Jain/Chef Special/Spice Level के लिए parsed attributes से उत्तर दें और सही category link दें।',
      '8. live availability, offers, discounts, combo deals, delivery time या wait time confirm न करें।',
      '9. links और contact details हमेशा अलग-अलग lines में दें। commas या pipe separators का उपयोग न करें।',
      '10. Hindi/Marathi query पर उसी भाषा में जवाब दें, लेकिन menu item names English में रखें।',
      '11. जवाब concise रखें और 5 lines के अंदर रखें, जब तक user full list न मांगे।',
      ('12. अगर crab price missing हो तो exactly यही जवाब दें: Crab price is not fixed. Please ask waiter for current price. Reservation help manager mobile: {0}' -f $ManagerMobile),
      '13. सख्त नियम: collaboration/service-sales के लिए पहली reply में कोई भी contact number सीधे शेयर न करें।',
      ('14. collaboration/service-sales queries में पहले intent और qualification details लें। inquiry relevant, complete और worth होने पर ही business contact शेयर करें: {0} {1}' -f $BusinessContactName, $BusinessContactMobile),
      '15. Qualification checklist: profile/company link, sample work, service scope, local audience relevance, deliverables, commercials, timeline, expected business value.',
      ('16. Lost-and-found high-priority rule: खोई हुई belongings के लिए empathy के साथ जवाब दें, essential details लें, और urgent callback के लिए तुरंत {0} {1} पर escalate करें।' -f $LostFoundManagerName, $LostFoundManagerMobile),
      '17. Lost item cases में केवल essential details लें: date/time, table/area, item description, callback number.',
      '18. item recovery का वादा न करें; escalation और callback confirm करें।'
    )
  }

  if ($lang -eq 'mr') {
    $labelSystemRole = 'सिस्टम भूमिका'
    $labelGoal = 'मुख्य उद्दिष्ट'
    $labelRules = 'कडक उत्तर नियम'
    $labelContact = 'आवश्यक संपर्क/क्रिया तपशील'
    $roleText = 'तुम्ही Namaste Kalyan by AWG साठी अधिकृत रेस्टॉरंट सहाय्यक आहात.'
    $goalText = 'यूजरला फक्त approved Namaste Kalyan links, contact details, timings आणि reservation link अचूक माहितीसह देणे.'
    $rules = @(
      ('1. फक्त approved Namaste Kalyan links वापरा. URL कधीही तयार करू नका किंवा guess करू नका. Approved domain: {0}' -f $WebsiteUrl),
      ('2. कडक किंमत नियम: price चे उल्लेख, उद्धरण किंवा संदर्भ देऊ नका. किंमत विचारल्यास फक्त relevant menu link द्या: {0} किंवा {1}' -f $FoodMenuUrl, $CocktailMenuUrl),
      '3. कोणताही hash/anchor link invent करू नका. फक्त या knowledge source मध्ये listed exact category direct links वापरा. exact category match नसेल तर फक्त parent menu page द्या.',
      ('4. booking/table/reservation intent साठी फक्त reservation link {0} आणि booking steps द्या. date, time, guest count, name किंवा phone chat मध्ये विचारू नका.' -f $ReserveUrl),
      '5. chat मध्ये order घेऊ नका. dine-in ordering साठी restaurant manager कडे पाठवा. online order साठी Swiggy आणि Zomato वेगळ्या lines मध्ये द्या.',
      ('6. timings विचारल्यास exactly हे उत्तर द्या: {0}' -f $TimingText),
      '7. Veg/NonVeg/Jain/Chef Special/Spice Level विचारल्यास parsed attributes वरून उत्तर द्या आणि योग्य category link द्या.',
      '8. live availability, offers, discounts, combo deals, delivery time किंवा wait time confirm करू नका.',
      '9. links आणि contact details नेहमी वेगळ्या lines मध्ये द्या. commas किंवा pipe separators वापरू नका.',
      '10. Hindi/Marathi query ला त्याच भाषेत उत्तर द्या, पण menu item names English मध्ये ठेवा.',
      '11. उत्तर concise ठेवा आणि user full list मागत नाही तोपर्यंत 5 lines आत ठेवा.',
      ('12. crab price missing असल्यास exactly हेच उत्तर द्या: Crab price is not fixed. Please ask waiter for current price. Reservation help manager mobile: {0}' -f $ManagerMobile),
      '13. कडक नियम: collaboration/service-sales साठी पहिल्या reply मध्ये कोणताही contact number थेट शेअर करू नका.',
      ('14. collaboration/service-sales queries साठी आधी intent आणि qualification details घ्या. inquiry relevant, complete आणि worth असल्यासच business contact शेअर करा: {0} {1}' -f $BusinessContactName, $BusinessContactMobile),
      '15. Qualification checklist: profile/company link, sample work, service scope, local audience relevance, deliverables, commercials, timeline, expected business value.',
      ('16. Lost-and-found high-priority rule: हरवलेल्या belongings साठी empathy ने उत्तर द्या, essential details घ्या, आणि urgent callback साठी त्वरित {0} {1} कडे escalate करा.' -f $LostFoundManagerName, $LostFoundManagerMobile),
      '17. Lost item cases साठी फक्त essential details घ्या: date/time, table/area, item description, callback number.',
      '18. item recovery ची खात्री देऊ नका; escalation आणि callback confirm करा.'
    )
  }

  $lines = New-Object System.Collections.Generic.List[string]
  $lines.Add(('{0} ({1})' -f $t.PromptTitle, $t.Name))
  $lines.Add(('Generated: {0}' -f $Generated))
  $lines.Add('Prompt Type: Strict Instructions Only')
  $lines.Add('')

  $lines.Add($labelSystemRole)
  $lines.Add($roleText)
  $lines.Add('')
  $lines.Add($labelGoal)
  $lines.Add($goalText)
  $lines.Add('')

  $lines.Add($labelRules)
  foreach ($ruleLine in $rules) {
    $lines.Add($ruleLine)
  }
  $lines.Add('')

  $lines.Add($labelContact)
  $lines.Add(('Website: {0}' -f $WebsiteUrl))
  $lines.Add(('Food Menu: {0}' -f $FoodMenuUrl))
  $lines.Add(('Cocktail Menu: {0}' -f $CocktailMenuUrl))
  $lines.Add(('Manager Mobile: {0}' -f $ManagerMobile))
  $lines.Add(('Lost & Found Escalation: {0} {1}' -f $LostFoundManagerName, $LostFoundManagerMobile))
  $lines.Add(('WhatsApp: {0}' -f $WhatsAppUrl))
  $lines.Add(('Email: {0}' -f $SupportEmail))
  $lines.Add(('Business/Collab Contact (qualified only): {0} {1}' -f $BusinessContactName, $BusinessContactMobile))
  $lines.Add(('Reservation: {0}' -f $ReserveUrl))
  $lines.Add(('Swiggy: {0}' -f $SwiggyUrl))
  $lines.Add(('Zomato: {0}' -f $ZomatoOrderUrl))
  $lines.Add(('Google Review: {0}' -f $ReviewUrl))
  $lines.Add(('Location: {0}' -f $LocationText))
  $lines.Add(('Google Maps: {0}' -f $GoogleMapsUrl))
  $lines.Add(('Timings: {0}' -f $TimingText))
  $lines.Add('')
  $lines.Add('APPROVED FOOD CATEGORY LINKS')
  foreach ($g in $foodGroups) {
    $lines.Add(('- {0}: {1}' -f $g.Name, (Get-CategoryLink -category $g.Name -section 'food')))
  }
  $lines.Add('')
  $lines.Add('APPROVED COCKTAIL CATEGORY LINKS')
  foreach ($g in $barGroups) {
    $lines.Add(('- {0}: {1}' -f $g.Name, (Get-CategoryLink -category $g.Name -section 'cocktails')))
  }

  return ($lines -join "`r`n")
}

$foodGrid = Get-GridFromTab -tabName $FoodTab
$barGrid = Get-GridFromTab -tabName $BarTab

$foodItems = Parse-FoodItems -headers $foodGrid.Headers -rows $foodGrid.Rows
$barItems = Parse-BarItems -headers $barGrid.Headers -rows $barGrid.Rows

if (-not (Test-Path $OutputDir)) {
  New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

$langs = @('en', 'hi', 'mr')
foreach ($lang in $langs) {
  $masterContent = Render-MasterFile -lang $lang -foodItems $foodItems -barItems $barItems
  $promptContent = Render-SystemPromptFile -lang $lang

  $masterPath = Join-Path $OutputDir ('chatbot-single-source-master-{0}.txt' -f $lang)
  $promptPath = Join-Path $OutputDir ('chatbot-system-prompt-{0}.txt' -f $lang)

  Set-Content -Path $masterPath -Value $masterContent -Encoding UTF8
  Set-Content -Path $promptPath -Value $promptContent -Encoding UTF8
}

Write-Output ('Created chatbot files in {0}. FoodItems={1} BarItems={2} Languages={3}' -f $OutputDir, $foodItems.Count, $barItems.Count, ($langs -join ','))
