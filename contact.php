<?php
error_reporting(E_ALL ^ E_NOTICE);

ob_start(); // Output Buffering Start

session_start();
$pageTitle = 'Home';
include 'init.php'; 

?>














	<!--
 <section class="relative sm:py-16 lg:py-24">
 <div class="absolute inset-0">
 <img class="object-cover w-full h-full" src="assets/img/map.png" alt="" />
 </div>
 <div class="absolute inset-0 bg-[#0000008a]"></div>


 <div class="py-10 bg-gray-50 sm:py-16 lg:py-24 max-w-2xl mx-auto">
 <div class="max-w-6xl px-4 mx-auto sm:px-6 lg:px-8">
 <div class="relative">
 <div class="aspect-w-4 aspect-h-3">
 <img class="object-cover w-full h-full rounded-3xl" src="assets/img/main-video.jpg" alt="" />
 </div>

 <div class="absolute inset-0 flex items-center justify-center">
 <div class="flex items-center justify-center rounded-full w-28 h-28 bg-white/20">
 <button type="button" class="flex items-center justify-center w-20 h-20 text-white transition-all duration-200 rounded-full bg-gradient-to-r from-[#000] to-[#f1d293] hover:opacity-90">
 <svg class="w-6 h-6 lg:w-8 lg:h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
 <path d="M8 6.82v10.36c0 .79.87 1.27 1.54.84l8.14-5.18c.62-.39.62-1.29 0-1.69L9.54 5.98C8.87 5.55 8 6.03 8 6.82z"></path>
 </svg>
 </button>
 </div>
 </div>

 </div>
 </div>
 </div>
 </section>

-->
































<section class="py-10 bg-gray-50 sm:py-16 lg:py-24">
 <div class="px-4 mx-auto sm:px-6 lg:px-8 max-w-7xl">
 <div class="max-w-2xl mx-auto text-center">
 <h2 class="text-3xl font-bold leading-tight text-gray-900 sm:text-4xl lg:text-5xl">إتصل بنا</h2>
 <p class="max-w-xl mx-auto mt-4 text-base leading-relaxed text-gray-500"></p>
 </div>

 <div class="max-w-5xl mx-auto mt-12 sm:mt-16">
 <div class="grid grid-cols-1 gap-6 px-8 text-center md:px-0 md:grid-cols-3">
 <div class="overflow-hidden bg-white rounded-xl">
 <div class="p-6">
 <svg class="flex-shrink-0 w-10 h-10 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path
 stroke-linecap="round"
 stroke-linejoin="round"
 stroke-width="1"
 d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
 />
 </svg>
 <p dir="ltr" class="mt-6 text-lg font-medium text-gray-900">+20-1552521511
</p>
 <p dir="ltr" class="mt-1 text-lg font-medium text-gray-900">+20-1552521511</p>
 </div>
 </div>

 <div class="overflow-hidden bg-white rounded-xl">
 <div class="p-6">
 <svg class="flex-shrink-0 w-10 h-10 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
 </svg>
 <p dir="ltr" class="mt-6 text-lg font-medium text-gray-900">contact@example.com</p>
 </div>
 </div>

 <div class="overflow-hidden bg-white rounded-xl">
 <div class="p-6">
 <svg class="flex-shrink-0 w-10 h-10 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
 </svg>
 <p class="mt-6 text-lg font-medium leading-relaxed text-gray-900">محمد عبدالحليم ابو غزاله, طريق المشير أحمد بدوي،, محافظة القاهرة‬, Badr, Egypt
</p>
 </div>
 </div>
 </div>

 <div class="mt-6 overflow-hidden bg-white rounded-xl">
 <div class="px-6 py-12 sm:p-12">
 <h3 class="text-3xl font-semibold text-center text-gray-900">أرسل لنا رسالة</h3>

 <form action="actions.php" method="POST" class="mt-14">
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
 <div>
 <label for="name" class="text-base font-medium text-gray-900"> اسمك الكريم </label>
 <div class="mt-2.5 relative">
 <input type="text" name="name" id="name" placeholder="أكتب اسمك الكامل" class="block w-full px-4 py-4 text-black placeholder-gray-500 transition-all duration-200 bg-white border border-gray-200 rounded-md focus:outline-none focus:border-blue-600 caret-blue-600" />
 </div>
 </div>

 <div>
 <label for="job" class="text-base font-medium text-gray-900"> الوظيفة </label>
 <div class="mt-2.5 relative">
 <input type="text" name="job" id="job" placeholder="ما هي وظيفتك الحالة" class="block w-full px-4 py-4 text-black placeholder-gray-500 transition-all duration-200 bg-white border border-gray-200 rounded-md focus:outline-none focus:border-blue-600 caret-blue-600" />
 </div>
 </div>

							<div>
 <label for="email" class="text-base font-medium text-gray-900"> البريد الإلكتروني </label>
 <div class="mt-2.5 relative">
 <input type="email" name="email" id="email" placeholder="أكتب بريدك الإلكتروني" class="text-left block w-full px-4 py-4 text-black placeholder-gray-500 transition-all duration-200 bg-white border border-gray-200 rounded-md focus:outline-none focus:border-blue-600 caret-blue-600" />
 </div>
 </div>

 <div>
 <label for="phone" class="text-base font-medium text-gray-900"> رقم الهاتف </label>
 <div class="mt-2.5 relative">
<div class="relative flex">
						<div class="absolute inset-y-0 left-0 flex items-center selectbox">
							<select required="required" id="country_code" name="country_code" class=" min-h-[50px] max-w-[100px] sm:max-w-[135px] h-full rounded-md block w-full rounded border-0 py-2 px-3.5 pl-3 placeholder-[#000] bg-[#86868617] dark:text-[#000] text-[#000] ">
								<option value="Null" disable="">إختر الدولة</option>
								<option data-countrycode="EG" value="20" selected="">Egypt (+20)</option>
								<option data-countrycode="KW" value="965">Kuwait (+965)</option>
								<option data-countrycode="SA" value="966">Saudi Arabia (+966)</option>
								<option data-countrycode="AE" value="971">United Arab Emirates (+971)</option>
								<option data-countrycode="IL" value="972">Palestine (+972)</option>
								<option data-countrycode="DZ" value="213">Algeria (+213)</option>
								<option data-countrycode="AD" value="376">Andorra (+376)</option>
								<option data-countrycode="AO" value="244">Angola (+244)</option>
								<option data-countrycode="AI" value="1264">Anguilla (+1264)</option>
								<option data-countrycode="AG" value="1268">Antigua &amp; Barbuda (+1268)</option>
								<option data-countrycode="AR" value="54">Argentina (+54)</option>
								<option data-countrycode="AM" value="374">Armenia (+374)</option>
								<option data-countrycode="AW" value="297">Aruba (+297)</option>
								<option data-countrycode="AU" value="61">Australia (+61)</option>
								<option data-countrycode="AT" value="43">Austria (+43)</option>
								<option data-countrycode="AZ" value="994">Azerbaijan (+994)</option>
								<option data-countrycode="BS" value="1242">Bahamas (+1242)</option>
								<option data-countrycode="BH" value="973">Bahrain (+973)</option>
								<option data-countrycode="BD" value="880">Bangladesh (+880)</option>
								<option data-countrycode="BB" value="1246">Barbados (+1246)</option>
								<option data-countrycode="BY" value="375">Belarus (+375)</option>
								<option data-countrycode="BE" value="32">Belgium (+32)</option>
								<option data-countrycode="BZ" value="501">Belize (+501)</option>
								<option data-countrycode="BJ" value="229">Benin (+229)</option>
								<option data-countrycode="BM" value="1441">Bermuda (+1441)</option>
								<option data-countrycode="BT" value="975">Bhutan (+975)</option>
								<option data-countrycode="BO" value="591">Bolivia (+591)</option>
								<option data-countrycode="BA" value="387">Bosnia Herzegovina (+387)</option>
								<option data-countrycode="BW" value="267">Botswana (+267)</option>
								<option data-countrycode="BR" value="55">Brazil (+55)</option>
								<option data-countrycode="BN" value="673">Brunei (+673)</option>
								<option data-countrycode="BG" value="359">Bulgaria (+359)</option>
								<option data-countrycode="BF" value="226">Burkina Faso (+226)</option>
								<option data-countrycode="BI" value="257">Burundi (+257)</option>
								<option data-countrycode="KH" value="855">Cambodia (+855)</option>
								<option data-countrycode="CM" value="237">Cameroon (+237)</option>
								<option data-countrycode="CA" value="1">Canada (+1)</option>
								<option data-countrycode="CV" value="238">Cape Verde Islands (+238)</option>
								<option data-countrycode="KY" value="1345">Cayman Islands (+1345)</option>
								<option data-countrycode="CF" value="236">Central African Republic (+236)</option>
								<option data-countrycode="CL" value="56">Chile (+56)</option>
								<option data-countrycode="CN" value="86">China (+86)</option>
								<option data-countrycode="CO" value="57">Colombia (+57)</option>
								<option data-countrycode="KM" value="269">Comoros (+269)</option>
								<option data-countrycode="CG" value="242">Congo (+242)</option>
								<option data-countrycode="CK" value="682">Cook Islands (+682)</option>
								<option data-countrycode="CR" value="506">Costa Rica (+506)</option>
								<option data-countrycode="HR" value="385">Croatia (+385)</option>
								<option data-countrycode="CU" value="53">Cuba (+53)</option>
								<option data-countrycode="CY" value="90392">Cyprus North (+90392)</option>
								<option data-countrycode="CY" value="357">Cyprus South (+357)</option>
								<option data-countrycode="CZ" value="42">Czech Republic (+42)</option>
								<option data-countrycode="DK" value="45">Denmark (+45)</option>
								<option data-countrycode="DJ" value="253">Djibouti (+253)</option>
								<option data-countrycode="DM" value="1809">Dominica (+1809)</option>
								<option data-countrycode="DO" value="1809">Dominican Republic (+1809)</option>
								<option data-countrycode="EC" value="593">Ecuador (+593)</option>
								<option data-countrycode="SV" value="503">El Salvador (+503)</option>
								<option data-countrycode="GQ" value="240">Equatorial Guinea (+240)</option>
								<option data-countrycode="ER" value="291">Eritrea (+291)</option>
								<option data-countrycode="EE" value="372">Estonia (+372)</option>
								<option data-countrycode="ET" value="251">Ethiopia (+251)</option>
								<option data-countrycode="FK" value="500">Falkland Islands (+500)</option>
								<option data-countrycode="FO" value="298">Faroe Islands (+298)</option>
								<option data-countrycode="FJ" value="679">Fiji (+679)</option>
								<option data-countrycode="FI" value="358">Finland (+358)</option>
								<option data-countrycode="FR" value="33">France (+33)</option>
								<option data-countrycode="GF" value="594">French Guiana (+594)</option>
								<option data-countrycode="PF" value="689">French Polynesia (+689)</option>
								<option data-countrycode="GA" value="241">Gabon (+241)</option>
								<option data-countrycode="GM" value="220">Gambia (+220)</option>
								<option data-countrycode="GE" value="7880">Georgia (+7880)</option>
								<option data-countrycode="DE" value="49">Germany (+49)</option>
								<option data-countrycode="GH" value="233">Ghana (+233)</option>
								<option data-countrycode="GI" value="350">Gibraltar (+350)</option>
								<option data-countrycode="GR" value="30">Greece (+30)</option>
								<option data-countrycode="GL" value="299">Greenland (+299)</option>
								<option data-countrycode="GD" value="1473">Grenada (+1473)</option>
								<option data-countrycode="GP" value="590">Guadeloupe (+590)</option>
								<option data-countrycode="GU" value="671">Guam (+671)</option>
								<option data-countrycode="GT" value="502">Guatemala (+502)</option>
								<option data-countrycode="GN" value="224">Guinea (+224)</option>
								<option data-countrycode="GW" value="245">Guinea - Bissau (+245)</option>
								<option data-countrycode="GY" value="592">Guyana (+592)</option>
								<option data-countrycode="HT" value="509">Haiti (+509)</option>
								<option data-countrycode="HN" value="504">Honduras (+504)</option>
								<option data-countrycode="HK" value="852">Hong Kong (+852)</option>
								<option data-countrycode="HU" value="36">Hungary (+36)</option>
								<option data-countrycode="IS" value="354">Iceland (+354)</option>
								<option data-countrycode="IN" value="91">India (+91)</option>
								<option data-countrycode="ID" value="62">Indonesia (+62)</option>
								<option data-countrycode="IR" value="98">Iran (+98)</option>
								<option data-countrycode="IQ" value="964">Iraq (+964)</option>
								<option data-countrycode="IE" value="353">Ireland (+353)</option>
								<option data-countrycode="IT" value="39">Italy (+39)</option>
								<option data-countrycode="JM" value="1876">Jamaica (+1876)</option>
								<option data-countrycode="JP" value="81">Japan (+81)</option>
								<option data-countrycode="JO" value="962">Jordan (+962)</option>
								<option data-countrycode="KZ" value="7">Kazakhstan (+7)</option>
								<option data-countrycode="KE" value="254">Kenya (+254)</option>
								<option data-countrycode="KI" value="686">Kiribati (+686)</option>
								<option data-countrycode="KP" value="850">Korea North (+850)</option>
								<option data-countrycode="KR" value="82">Korea South (+82)</option>
								<option data-countrycode="KG" value="996">Kyrgyzstan (+996)</option>
								<option data-countrycode="LA" value="856">Laos (+856)</option>
								<option data-countrycode="LV" value="371">Latvia (+371)</option>
								<option data-countrycode="LB" value="961">Lebanon (+961)</option>
								<option data-countrycode="LS" value="266">Lesotho (+266)</option>
								<option data-countrycode="LR" value="231">Liberia (+231)</option>
								<option data-countrycode="LY" value="218">Libya (+218)</option>
								<option data-countrycode="LI" value="417">Liechtenstein (+417)</option>
								<option data-countrycode="LT" value="370">Lithuania (+370)</option>
								<option data-countrycode="LU" value="352">Luxembourg (+352)</option>
								<option data-countrycode="MO" value="853">Macao (+853)</option>
								<option data-countrycode="MK" value="389">Macedonia (+389)</option>
								<option data-countrycode="MG" value="261">Madagascar (+261)</option>
								<option data-countrycode="MW" value="265">Malawi (+265)</option>
								<option data-countrycode="MY" value="60">Malaysia (+60)</option>
								<option data-countrycode="MV" value="960">Maldives (+960)</option>
								<option data-countrycode="ML" value="223">Mali (+223)</option>
								<option data-countrycode="MT" value="356">Malta (+356)</option>
								<option data-countrycode="MH" value="692">Marshall Islands (+692)</option>
								<option data-countrycode="MQ" value="596">Martinique (+596)</option>
								<option data-countrycode="MR" value="222">Mauritania (+222)</option>
								<option data-countrycode="YT" value="269">Mayotte (+269)</option>
								<option data-countrycode="MX" value="52">Mexico (+52)</option>
								<option data-countrycode="FM" value="691">Micronesia (+691)</option>
								<option data-countrycode="MD" value="373">Moldova (+373)</option>
								<option data-countrycode="MC" value="377">Monaco (+377)</option>
								<option data-countrycode="MN" value="976">Mongolia (+976)</option>
								<option data-countrycode="MS" value="1664">Montserrat (+1664)</option>
								<option data-countrycode="MA" value="212">Morocco (+212)</option>
								<option data-countrycode="MZ" value="258">Mozambique (+258)</option>
								<option data-countrycode="MN" value="95">Myanmar (+95)</option>
								<option data-countrycode="NA" value="264">Namibia (+264)</option>
								<option data-countrycode="NR" value="674">Nauru (+674)</option>
								<option data-countrycode="NP" value="977">Nepal (+977)</option>
								<option data-countrycode="NL" value="31">Netherlands (+31)</option>
								<option data-countrycode="NC" value="687">New Caledonia (+687)</option>
								<option data-countrycode="NZ" value="64">New Zealand (+64)</option>
								<option data-countrycode="NI" value="505">Nicaragua (+505)</option>
								<option data-countrycode="NE" value="227">Niger (+227)</option>
								<option data-countrycode="NG" value="234">Nigeria (+234)</option>
								<option data-countrycode="NU" value="683">Niue (+683)</option>
								<option data-countrycode="NF" value="672">Norfolk Islands (+672)</option>
								<option data-countrycode="NP" value="670">Northern Marianas (+670)</option>
								<option data-countrycode="NO" value="47">Norway (+47)</option>
								<option data-countrycode="OM" value="968">Oman (+968)</option>
								<option data-countrycode="PW" value="680">Palau (+680)</option>
								<option data-countrycode="PA" value="507">Panama (+507)</option>
								<option data-countrycode="PG" value="675">Papua New Guinea (+675)</option>
								<option data-countrycode="PY" value="595">Paraguay (+595)</option>
								<option data-countrycode="PE" value="51">Peru (+51)</option>
								<option data-countrycode="PH" value="63">Philippines (+63)</option>
								<option data-countrycode="PL" value="48">Poland (+48)</option>
								<option data-countrycode="PT" value="351">Portugal (+351)</option>
								<option data-countrycode="PR" value="1787">Puerto Rico (+1787)</option>
								<option data-countrycode="QA" value="974">Qatar (+974)</option>
								<option data-countrycode="RE" value="262">Reunion (+262)</option>
								<option data-countrycode="RO" value="40">Romania (+40)</option>
								<option data-countrycode="RU" value="7">Russia (+7)</option>
								<option data-countrycode="RW" value="250">Rwanda (+250)</option>
								<option data-countrycode="SM" value="378">San Marino (+378)</option>
								<option data-countrycode="ST" value="239">Sao Tome &amp; Principe (+239)</option>
								<option data-countrycode="SN" value="221">Senegal (+221)</option>
								<option data-countrycode="CS" value="381">Serbia (+381)</option>
								<option data-countrycode="SC" value="248">Seychelles (+248)</option>
								<option data-countrycode="SL" value="232">Sierra Leone (+232)</option>
								<option data-countrycode="SG" value="65">Singapore (+65)</option>
								<option data-countrycode="SK" value="421">Slovak Republic (+421)</option>
								<option data-countrycode="SI" value="386">Slovenia (+386)</option>
								<option data-countrycode="SB" value="677">Solomon Islands (+677)</option>
								<option data-countrycode="SO" value="252">Somalia (+252)</option>
								<option data-countrycode="ZA" value="27">South Africa (+27)</option>
								<option data-countrycode="ES" value="34">Spain (+34)</option>
								<option data-countrycode="LK" value="94">Sri Lanka (+94)</option>
								<option data-countrycode="SH" value="290">St. Helena (+290)</option>
								<option data-countrycode="KN" value="1869">St. Kitts (+1869)</option>
								<option data-countrycode="SC" value="1758">St. Lucia (+1758)</option>
								<option data-countrycode="SD" value="249">Sudan (+249)</option>
								<option data-countrycode="SR" value="597">Suriname (+597)</option>
								<option data-countrycode="SZ" value="268">Swaziland (+268)</option>
								<option data-countrycode="SE" value="46">Sweden (+46)</option>
								<option data-countrycode="CH" value="41">Switzerland (+41)</option>
								<option data-countrycode="SI" value="963">Syria (+963)</option>
								<option data-countrycode="TW" value="886">Taiwan (+886)</option>
								<option data-countrycode="TJ" value="7">Tajikstan (+7)</option>
								<option data-countrycode="TH" value="66">Thailand (+66)</option>
								<option data-countrycode="TG" value="228">Togo (+228)</option>
								<option data-countrycode="TO" value="676">Tonga (+676)</option>
								<option data-countrycode="TT" value="1868">Trinidad &amp; Tobago (+1868)</option>
								<option data-countrycode="TN" value="216">Tunisia (+216)</option>
								<option data-countrycode="TR" value="90">Turkey (+90)</option>
								<option data-countrycode="TM" value="7">Turkmenistan (+7)</option>
								<option data-countrycode="TM" value="993">Turkmenistan (+993)</option>
								<option data-countrycode="TC" value="1649">Turks &amp; Caicos Islands (+1649)</option>
								<option data-countrycode="TV" value="688">Tuvalu (+688)</option>
								<option data-countrycode="UG" value="256">Uganda (+256)</option>
								<option data-countrycode="GB" value="44">UK (+44)</option>
								<option data-countrycode="UA" value="380">Ukraine (+380)</option>
								<option data-countrycode="UY" value="598">Uruguay (+598)</option>
								<option data-countrycode="US" value="1">USA (+1)</option>
								<option data-countrycode="UZ" value="7">Uzbekistan (+7)</option>
								<option data-countrycode="VU" value="678">Vanuatu (+678)</option>
								<option data-countrycode="VA" value="379">Vatican City (+379)</option>
								<option data-countrycode="VE" value="58">Venezuela (+58)</option>
								<option data-countrycode="VN" value="84">Vietnam (+84)</option>
								<option data-countrycode="VG" value="84">Virgin Islands - British (+1284)</option>
								<option data-countrycode="VI" value="84">Virgin Islands - US (+1340)</option>
								<option data-countrycode="WF" value="681">Wallis &amp; Futuna (+681)</option>
								<option data-countrycode="YE" value="969">Yemen (North)(+969)</option>
								<option data-countrycode="YE" value="967">Yemen (South)(+967)</option>
								<option data-countrycode="ZM" value="260">Zambia (+260)</option>
								<option data-countrycode="ZW" value="263">Zimbabwe (+263)</option>

							</select>
						</div>
						<input required="required" type="number" name="phone" autocomplete="tel" class="text-[#000] block w-full rounded border border-gray-200 py-2 px-3.5 pl-24 sm:pl-32 min-h-[50px] placeholder-gray-500 bg-[#fff] dark:text-[#9e9e9e]" placeholder="رقم هاتفك">
						<input required="required" type="text" name="this_url" autocomplete="tel" class="hidden text-[#000] block w-full rounded border-0 py-2 px-3.5 pl-32 min-h-[50px] placeholder-gray-500 bg-[#fff] dark:text-[#9e9e9e]" value="https://start.com.eg/contact.php">
					</div>
								</div>
 </div>


 <div class="sm:col-span-2">
 <label for="msg" class="text-base font-medium text-gray-900"> الرسالة </label>
 <div class="mt-2.5 relative">
 <textarea name="msg" id="msg" placeholder="" class="block w-full px-4 py-4 text-black placeholder-gray-500 transition-all duration-200 bg-white border border-gray-200 rounded-md resize-y focus:outline-none focus:border-blue-600 caret-blue-600" rows="4"></textarea>
 </div>
 </div>

 <div class="sm:col-span-2">
								<input type="submit" class="cursor-pointer inline-flex items-center justify-center w-full px-4 py-4 mt-2 text-base font-semibold text-white transition-all duration-200 bg-[#f1d293] border border-transparent rounded-md focus:outline-none hover:bg-blue-700 focus:bg-blue-700" value="إرسال" name="contactus">
 </div>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</section>

	
	
	
	
	



<?php
include 'includes/templates/footer.php'; 

ob_end_flush();
?>