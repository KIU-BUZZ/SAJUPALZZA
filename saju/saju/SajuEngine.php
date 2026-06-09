<?php
/**
 * ========================================
 * 사주팔자 계산 엔진 (명리학 정밀 버전)
 * ========================================
 * 
 * 한국 전통 명리학 이론 기반 정밀 구현
 * 
 * [계산 정확도]
 * - 년주: 입춘 절입 시각 기준 (1940~2030 정밀 데이터)
 * - 월주: 24절기 절입 기준 + 오호둔갑법
 * - 일주: 율리우스 적일(Julian Day) 기반 60갑자 순환
 * - 시주: 일간 기준 오자원둔법(五子元遁法)
 * 
 * [분석 기능]
 * - 지장간(地藏干): 지지에 암장된 천간 (여기/중기/본기 + 비율)
 * - 십성(十星): 천간+지장간 모두 포함한 십성 계산 및 분포
 * - 공망(空亡): 일주 기준 공망 계산
 * - 합충형파해: 육합/삼합/방합/형/충/파/해 + 천간합/천간충
 * - 신강/신약: 월령·지지근·지장간 비율 반영 정밀 판별
 * - 용신(用神)/희신(喜神)/기신(忌神)/구신(仇神) 4신 분석
 * - 12운성: 천간별 지지 12운성 계산
 */

class SajuEngine {

    // ============================================================
    // 기초 데이터
    // ============================================================

    // 천간 (10 Heavenly Stems)
    const CHEONGAN = ['갑','을','병','정','무','기','경','신','임','계'];
    const CHEONGAN_HANJA = ['甲','乙','丙','丁','戊','己','庚','辛','壬','癸'];

    // 지지 (12 Earthly Branches)
    const JIJI = ['자','축','인','묘','진','사','오','미','신','유','술','해'];
    const JIJI_HANJA = ['子','丑','寅','卯','辰','巳','午','未','申','酉','戌','亥'];

    // 오행
    const OHANG = ['목','화','토','금','수'];
    const OHANG_HANJA = ['木','火','土','金','水'];
    const OHANG_COLORS = ['#4CAF50','#F44336','#FF9800','#FFD700','#2196F3'];

    // 천간→오행
    const CHEONGAN_OHANG = [
        '갑'=>'목','을'=>'목','병'=>'화','정'=>'화','무'=>'토',
        '기'=>'토','경'=>'금','신'=>'금','임'=>'수','계'=>'수'
    ];
    // 지지→오행 (본기 기준)
    const JIJI_OHANG = [
        '자'=>'수','축'=>'토','인'=>'목','묘'=>'목',
        '진'=>'토','사'=>'화','오'=>'화','미'=>'토',
        '신'=>'금','유'=>'금','술'=>'토','해'=>'수'
    ];
    // 천간 음양 (0=양, 1=음)
    const CHEONGAN_YINYANG = [0,1,0,1,0,1,0,1,0,1];
    // 지지 음양
    const JIJI_YINYANG = [0,1,0,1,0,1,0,1,0,1,0,1];

    // ============================================================
    // 지장간 (地藏干) - 지지에 숨어있는 천간
    // ============================================================
    // 각 지지마다 1~3개의 천간이 숨어 있습니다.
    // [천간, 비율(힘의 세기)] 형태
    // 순서: 여기(餘氣, 이전 계절 잔여) → 중기(中氣) → 본기(本氣, 핵심)
    const JIJANGGAN = [
        '자' => [['임',0.3],['계',0.7]],                // 子: 임수(여기)+계수(본기)
        '축' => [['계',0.3],['신',0.3],['기',0.4]],     // 丑: 계수+신금+기토(본기)
        '인' => [['무',0.3],['병',0.3],['갑',0.4]],     // 寅: 무토+병화+갑목(본기)
        '묘' => [['갑',0.3],['을',0.7]],                // 卯: 갑목(여기)+을목(본기)
        '진' => [['을',0.3],['계',0.3],['무',0.4]],     // 辰: 을목+계수+무토(본기)
        '사' => [['무',0.3],['경',0.3],['병',0.4]],     // 巳: 무토+경금+병화(본기)
        '오' => [['병',0.3],['기',0.3],['정',0.4]],     // 午: 병화+기토+정화(본기)
        '미' => [['정',0.3],['을',0.3],['기',0.4]],     // 未: 정화+을목+기토(본기)
        '신' => [['무',0.3],['임',0.3],['경',0.4]],     // 申: 무토+임수+경금(본기)
        '유' => [['경',0.3],['신',0.7]],                // 酉: 경금(여기)+신금(본기)
        '술' => [['신',0.3],['정',0.3],['무',0.4]],     // 戌: 신금+정화+무토(본기)
        '해' => [['무',0.3],['갑',0.3],['임',0.4]],     // 亥: 무토+갑목+임수(본기)
    ];

    // ============================================================
    // 절기 데이터 (사주 월 계산의 핵심)
    // ============================================================
    // 사주의 월은 음력이 아닌 절기(節氣)로 결정됩니다.
    // 각 사주월의 시작 절기, 양력 해당월, 평균 절입일
    const JEOLGI_MAP = [
        1  => ['입춘',315,2,4],   // 인월(寅月) 시작
        2  => ['경칩',345,3,6],   // 묘월(卯月)
        3  => ['청명',15,4,5],    // 진월(辰月)
        4  => ['입하',45,5,6],    // 사월(巳月)
        5  => ['망종',75,6,6],    // 오월(午月)
        6  => ['소서',105,7,7],   // 미월(未月)
        7  => ['입추',135,8,7],   // 신월(申月)
        8  => ['백로',165,9,8],   // 유월(酉月)
        9  => ['한로',195,10,8],  // 술월(戌月)
        10 => ['입동',225,11,7],  // 해월(亥月)
        11 => ['대설',255,12,7],  // 자월(子月)
        12 => ['소한',285,1,6],   // 축월(丑月)
    ];

    // 연도별 입춘 시각 [월,일,시,분] (1940~2030)
    private static $ipchunData = [
        1940=>[2,5,2,8],1941=>[2,4,8,1],1942=>[2,4,13,49],1943=>[2,4,19,40],
        1944=>[2,5,1,23],1945=>[2,4,7,20],1946=>[2,4,13,5],1947=>[2,4,18,51],
        1948=>[2,5,0,43],1949=>[2,4,6,30],1950=>[2,4,12,22],1951=>[2,4,18,14],
        1952=>[2,5,0,0],1953=>[2,4,5,46],1954=>[2,4,11,31],1955=>[2,4,17,18],
        1956=>[2,4,23,13],1957=>[2,4,4,55],1958=>[2,4,10,49],1959=>[2,4,16,42],
        1960=>[2,4,22,23],1961=>[2,4,4,4],1962=>[2,4,9,58],1963=>[2,4,15,45],
        1964=>[2,4,21,37],1965=>[2,4,3,23],1966=>[2,4,9,14],1967=>[2,4,14,55],
        1968=>[2,4,20,48],1969=>[2,4,2,33],1970=>[2,4,8,28],1971=>[2,4,14,17],
        1972=>[2,4,20,6],1973=>[2,4,1,49],1974=>[2,4,7,42],1975=>[2,4,13,28],
        1976=>[2,4,19,19],1977=>[2,4,1,4],1978=>[2,4,6,50],1979=>[2,4,12,40],
        1980=>[2,4,18,29],1981=>[2,4,0,15],1982=>[2,4,6,7],1983=>[2,4,11,57],
        1984=>[2,4,17,42],1985=>[2,3,23,28],1986=>[2,4,5,17],1987=>[2,4,11,7],
        1988=>[2,4,16,53],1989=>[2,3,22,36],1990=>[2,4,4,27],1991=>[2,4,10,15],
        1992=>[2,4,16,4],1993=>[2,3,21,55],1994=>[2,4,3,39],1995=>[2,4,9,30],
        1996=>[2,4,15,19],1997=>[2,3,21,3],1998=>[2,4,2,53],1999=>[2,4,8,42],
        2000=>[2,4,14,32],2001=>[2,3,20,16],2002=>[2,4,2,8],2003=>[2,4,7,57],
        2004=>[2,4,13,46],2005=>[2,3,19,34],2006=>[2,4,1,22],2007=>[2,4,7,14],
        2008=>[2,4,12,58],2009=>[2,3,18,50],2010=>[2,4,0,42],2011=>[2,4,6,30],
        2012=>[2,4,12,22],2013=>[2,3,18,13],2014=>[2,4,0,3],2015=>[2,4,5,55],
        2016=>[2,4,11,46],2017=>[2,3,17,34],2018=>[2,3,23,25],2019=>[2,4,5,14],
        2020=>[2,4,11,3],2021=>[2,3,16,59],2022=>[2,3,22,51],2023=>[2,4,4,43],
        2024=>[2,4,10,27],2025=>[2,3,16,10],2026=>[2,3,22,2],2027=>[2,4,3,46],
        2028=>[2,4,9,38],2029=>[2,3,15,20],2030=>[2,3,21,8],
    ];

    // 연도별 월별 절기 절입일 [월,일] (2020-2030 정밀)
    private static $monthlyJeolgiData = [
        2020=>[[2,4],[3,5],[4,4],[5,5],[6,5],[7,6],[8,7],[9,7],[10,8],[11,7],[12,7],[1,6]],
        2021=>[[2,3],[3,5],[4,4],[5,5],[6,5],[7,7],[8,7],[9,7],[10,8],[11,7],[12,7],[1,5]],
        2022=>[[2,4],[3,5],[4,5],[5,5],[6,6],[7,7],[8,7],[9,7],[10,8],[11,7],[12,7],[1,5]],
        2023=>[[2,4],[3,6],[4,5],[5,6],[6,6],[7,7],[8,7],[9,8],[10,8],[11,7],[12,7],[1,6]],
        2024=>[[2,4],[3,5],[4,4],[5,5],[6,5],[7,6],[8,7],[9,7],[10,8],[11,7],[12,7],[1,6]],
        2025=>[[2,3],[3,5],[4,4],[5,5],[6,5],[7,7],[8,7],[9,7],[10,8],[11,7],[12,7],[1,5]],
        2026=>[[2,4],[3,5],[4,5],[5,5],[6,6],[7,7],[8,7],[9,7],[10,8],[11,7],[12,7],[1,5]],
        2027=>[[2,4],[3,6],[4,5],[5,6],[6,6],[7,7],[8,7],[9,8],[10,8],[11,7],[12,7],[1,6]],
        2028=>[[2,4],[3,5],[4,4],[5,5],[6,5],[7,6],[8,7],[9,7],[10,7],[11,7],[12,7],[1,6]],
        2029=>[[2,3],[3,5],[4,4],[5,5],[6,5],[7,7],[8,7],[9,7],[10,8],[11,7],[12,7],[1,5]],
        2030=>[[2,4],[3,5],[4,5],[5,5],[6,6],[7,7],[8,7],[9,7],[10,8],[11,7],[12,7],[1,5]],
    ];

    // ============================================================
    // 합충형파해 관계 상수
    // ============================================================
    const YUKHAP = [['자','축'],['인','해'],['묘','술'],['진','유'],['사','신'],['오','미']];
    const YUKHAP_ELEMENT = ['토','목','화','금','수','토'];
    const SAMHAP = [['신','자','진'],['해','묘','미'],['인','오','술'],['사','유','축']];
    const SAMHAP_ELEMENT = ['수','목','화','금'];
    const BANGHAP = [['인','묘','진'],['사','오','미'],['신','유','술'],['해','자','축']];
    const BANGHAP_ELEMENT = ['목','화','금','수'];
    const HYUNG = [['인','사','신'],['축','술','미'],['자','묘'],['진','진'],['오','오'],['유','유'],['해','해']];
    const CHUNG = [['자','오'],['축','미'],['인','신'],['묘','유'],['진','술'],['사','해']];
    const PA = [['자','유'],['축','진'],['인','해'],['묘','오'],['사','신'],['미','술']];
    const HAE_REL = [['자','미'],['축','오'],['인','사'],['묘','진'],['신','해'],['유','술']];
    const CHEONGAN_HAP = [['갑','기'],['을','경'],['병','신'],['정','임'],['무','계']];
    const CHEONGAN_HAP_ELEMENT = ['토','금','수','목','화'];
    const CHEONGAN_CHUNG = [['갑','경'],['을','신'],['병','임'],['정','계']];

    // 띠(12지 동물)
    const ZODIAC_ANIMALS = ['자'=>'쥐','축'=>'소','인'=>'호랑이','묘'=>'토끼','진'=>'용','사'=>'뱀','오'=>'말','미'=>'양','신'=>'원숭이','유'=>'닭','술'=>'개','해'=>'돼지'];
    // 시간→지지
    const HOUR_TO_JIJI = [23=>0,0=>0,1=>1,2=>1,3=>2,4=>2,5=>3,6=>3,7=>4,8=>4,9=>5,10=>5,11=>6,12=>6,13=>7,14=>7,15=>8,16=>8,17=>9,18=>9,19=>10,20=>10,21=>11,22=>11];
    const SIJI_NAMES = ['자시(子時) 23:00~01:00','축시(丑時) 01:00~03:00','인시(寅時) 03:00~05:00','묘시(卯時) 05:00~07:00','진시(辰時) 07:00~09:00','사시(巳時) 09:00~11:00','오시(午時) 11:00~13:00','미시(未時) 13:00~15:00','신시(申時) 15:00~17:00','유시(酉時) 17:00~19:00','술시(戌時) 19:00~21:00','해시(亥時) 21:00~23:00'];

    // 12운성 시작 지지 인덱스 (천간별)
    const TWELVE_STAGES_START = [11,6,2,9,2,9,5,0,8,3];
    const TWELVE_STAGES = ['장생','목욕','관대','건록','제왕','쇠','병','사','묘','절','태','양'];

    // ============================================================
    // 십성(十星/十神) 이름 배열
    // ============================================================
    const SIPSIN_NAMES = ['비견','겁재','식신','상관','편재','정재','편관','정관','편인','정인'];
    const SIPSIN_INFO = [
        '비견'=>['desc'=>'나와 같은 오행, 같은 음양','meaning'=>'자존심, 독립심, 경쟁심, 형제/동료'],
        '겁재'=>['desc'=>'나와 같은 오행, 다른 음양','meaning'=>'승부욕, 대인관계, 경쟁, 외향적'],
        '식신'=>['desc'=>'내가 생하는 오행, 같은 음양','meaning'=>'표현력, 창의성, 식복, 자녀운'],
        '상관'=>['desc'=>'내가 생하는 오행, 다른 음양','meaning'=>'반항심, 창의력, 언변, 자유분방'],
        '편재'=>['desc'=>'내가 극하는 오행, 같은 음양','meaning'=>'투기성 재물, 아버지, 사업 수완'],
        '정재'=>['desc'=>'내가 극하는 오행, 다른 음양','meaning'=>'안정적 재물, 저축, 근면, 처/여자'],
        '편관'=>['desc'=>'나를 극하는 오행, 같은 음양','meaning'=>'권력, 명예, 도전, 변화, 스트레스'],
        '정관'=>['desc'=>'나를 극하는 오행, 다른 음양','meaning'=>'명예, 직장, 질서, 도덕성, 남편'],
        '편인'=>['desc'=>'나를 생하는 오행, 같은 음양','meaning'=>'학문, 비밀, 외로움, 특수 재능'],
        '정인'=>['desc'=>'나를 생하는 오행, 다른 음양','meaning'=>'학문, 어머니, 인자함, 안정, 명예'],
    ];

    // ============================================================
    // 인스턴스 변수
    // ============================================================
    private $birthYear, $birthMonth, $birthDay, $birthHour;
    private $gender, $calendarType;
    private $solarDate;
    private $yearPillar, $monthPillar, $dayPillar, $hourPillar;

    // ============================================================
    // 생성자
    // ============================================================
    public function __construct($year, $month, $day, $hour, $gender, $calendarType = 'solar') {
        $this->birthYear = (int)$year;
        $this->birthMonth = (int)$month;
        $this->birthDay = (int)$day;
        $this->birthHour = ($hour !== null && $hour !== '' && (int)$hour >= 0) ? (int)$hour : null;
        $this->gender = $gender;
        $this->calendarType = $calendarType;

        if ($calendarType === 'lunar') {
            $this->convertLunarToSolar();
        } else {
            $this->solarDate = ['year'=>$this->birthYear,'month'=>$this->birthMonth,'day'=>$this->birthDay];
        }
        $this->calculate();
    }

    // ============================================================
    // 음력→양력 변환
    // ============================================================
    private function convertLunarToSolar() {
        $y = $this->birthYear; $m = $this->birthMonth; $d = $this->birthDay;
        $lunarNewYear = $this->estimateLunarNewYear($y);
        $lunarDaysSinceNewYear = ($m - 1) * 29.5 + $d - 1;
        $ts = mktime(0,0,0,$lunarNewYear[0],$lunarNewYear[1],$y);
        $ts += (int)($lunarDaysSinceNewYear * 86400);
        $this->solarDate = ['year'=>(int)date('Y',$ts),'month'=>(int)date('n',$ts),'day'=>(int)date('j',$ts)];
    }
    private function estimateLunarNewYear($year) {
        $k = round(($year - 2000) * 12.3685);
        $jde = 2451550.09766 + 29.530588861 * $k;
        $ts = (int)(($jde - 2440587.5) * 86400);
        $m = (int)date('n',$ts); $d = (int)date('j',$ts);
        if ($m==1 && $d<21) { $jde+=29.53; $ts=(int)(($jde-2440587.5)*86400); $m=(int)date('n',$ts); $d=(int)date('j',$ts); }
        return [$m,$d];
    }

    // ============================================================
    // 사주 4주 계산
    // ============================================================
    private function calculate() {
        $this->calculateYearPillar();
        $this->calculateMonthPillar();
        $this->calculateDayPillar();
        $this->calculateHourPillar();
    }

    /** 년주 - 입춘 시각 기준 */
    private function calculateYearPillar() {
        $year = $this->solarDate['year']; $month = $this->solarDate['month']; $day = $this->solarDate['day'];
        $hour = $this->birthHour ?? 12;
        $ipchun = self::$ipchunData[$year] ?? [2,4,12,0];
        $beforeIpchun = false;
        if ($month < $ipchun[0]) $beforeIpchun = true;
        elseif ($month==$ipchun[0]) {
            if ($day < $ipchun[1]) $beforeIpchun = true;
            elseif ($day==$ipchun[1] && $hour < ($ipchun[2]??0)) $beforeIpchun = true;
        }
        if ($beforeIpchun) $year--;
        $stemIndex = (($year-4)%10+10)%10;
        $branchIndex = (($year-4)%12+12)%12;
        $this->yearPillar = [$stemIndex,$branchIndex];
    }

    /** 월주 - 절기 기준 + 오호둔갑법 */
    private function calculateMonthPillar() {
        $year=$this->solarDate['year']; $month=$this->solarDate['month']; $day=$this->solarDate['day'];
        $sajuMonth = $this->getSajuMonth($year,$month,$day);
        $monthBranch = ($sajuMonth+1)%12;
        $yearStem = $this->yearPillar[0];
        $monthStemStart = ($yearStem%5)*2+2;
        $monthStem = ($monthStemStart+$sajuMonth-1)%10;
        $this->monthPillar = [$monthStem,$monthBranch];
    }

    private function getSajuMonth($year,$month,$day) {
        if (isset(self::$monthlyJeolgiData[$year])) {
            $data = self::$monthlyJeolgiData[$year];
            for ($sm=0;$sm<12;$sm++) {
                $cur=$data[$sm]; $next=($sm<11)?$data[$sm+1]:(self::$monthlyJeolgiData[$year+1][0]??[2,4]);
                $sajuMonth=$sm+1;
                if ($cur[0]>$next[0]) {
                    if (($month>$cur[0]||($month==$cur[0]&&$day>=$cur[1]))||($month<$next[0]||($month==$next[0]&&$day<$next[1]))) return $sajuMonth;
                } else {
                    if (($month>$cur[0]||($month==$cur[0]&&$day>=$cur[1]))&&($month<$next[0]||($month==$next[0]&&$day<$next[1]))) return $sajuMonth;
                }
            }
        }
        for ($sm=1;$sm<=12;$sm++) {
            $cur=self::getJeolgiDateGeneric($year,$sm); $next=($sm<12)?self::getJeolgiDateGeneric($year,$sm+1):self::getJeolgiDateGeneric($year+1,1);
            if ($cur[0]>$next[0]) {
                if (($month>$cur[0]||($month==$cur[0]&&$day>=$cur[1]))||($month<$next[0]||($month==$next[0]&&$day<$next[1]))) return $sm;
            } else {
                if (($month>$cur[0]||($month==$cur[0]&&$day>=$cur[1]))&&($month<$next[0]||($month==$next[0]&&$day<$next[1]))) return $sm;
            }
        }
        return 1;
    }
    private static function getJeolgiDateGeneric($year,$sajuMonth) {
        $info=self::JEOLGI_MAP[$sajuMonth]; $day=$info[3]+(($year%4===3)?-1:0);
        return [$info[2],max(1,$day)];
    }

    /** 일주 - 율리우스 적일 기반 60갑자 */
    private function calculateDayPillar() {
        $jd = $this->getJulianDay($this->solarDate['year'],$this->solarDate['month'],$this->solarDate['day']);
        $dayIndex = (($jd-2451551)%60+60)%60; // 2000-01-07=甲子일
        // 야자시 보정: 23시 이후는 다음 일주
        if ($this->birthHour!==null && $this->birthHour>=23) $dayIndex = ($dayIndex+1)%60;
        $this->dayPillar = [$dayIndex%10, $dayIndex%12];
    }

    /** 시주 - 오자원둔법 */
    private function calculateHourPillar() {
        if ($this->birthHour===null) { $this->hourPillar=[null,null]; return; }
        $hourBranch = self::HOUR_TO_JIJI[$this->birthHour];
        $hourStem = (($this->dayPillar[0]%5)*2+$hourBranch)%10;
        $this->hourPillar = [$hourStem,$hourBranch];
    }

    private function getJulianDay($y,$m,$d) {
        if ($m<=2) { $y--; $m+=12; }
        $A=floor($y/100); $B=2-$A+floor($A/4);
        return (int)(floor(365.25*($y+4716))+floor(30.6001*($m+1))+$d+$B-1524);
    }

    // ============================================================
    // 십성(十星) 계산 — 천간+지장간 모두 포함
    // ============================================================
    
    /**
     * 두 오행/음양 간의 십성 관계를 구합니다.
     */
    public static function getSipsin($dayElement, $dayYinyang, $targetElement, $targetYinyang) {
        $sameYY = ($dayYinyang === $targetYinyang);
        $cycle = self::OHANG;
        $di = array_search($dayElement, $cycle);
        $ti = array_search($targetElement, $cycle);
        $diff = ($ti - $di + 5) % 5;
        switch ($diff) {
            case 0: return $sameYY ? '비견' : '겁재';
            case 1: return $sameYY ? '식신' : '상관';
            case 2: return $sameYY ? '편재' : '정재';
            case 3: return $sameYY ? '편관' : '정관';
            case 4: return $sameYY ? '편인' : '정인';
        }
        return '비견';
    }

    /**
     * 사주 전체 십성 분석 (천간+지장간 포함)
     * 결과: 각 기둥의 천간 십성 + 지지 십성 + 지장간 십성 + 전체 분포
     */
    public function analyzeSipsinFull() {
        $dayMaster = $this->getDayMaster();
        $dayElement = $this->getDayMasterElement();
        $dayYY = self::CHEONGAN_YINYANG[$this->dayPillar[0]];

        $pillars = [
            ['name'=>'년주','pillar'=>$this->yearPillar],
            ['name'=>'월주','pillar'=>$this->monthPillar],
            ['name'=>'일주','pillar'=>$this->dayPillar],
            ['name'=>'시주','pillar'=>$this->hourPillar],
        ];

        $result = [];
        $distribution = array_fill_keys(self::SIPSIN_NAMES, 0);

        foreach ($pillars as $pi => $p) {
            $pillar = $p['pillar'];
            if ($pillar[0] === null) {
                $result[] = ['name'=>$p['name'],'stem_sipsin'=>'-','branch_sipsin'=>'-','jijanggan_sipsin'=>[],'twelve_stage'=>'-'];
                continue;
            }

            $stem = self::CHEONGAN[$pillar[0]];
            $branch = self::JIJI[$pillar[1]];
            $stemEl = self::CHEONGAN_OHANG[$stem];
            $stemYY = self::CHEONGAN_YINYANG[$pillar[0]];
            $branchEl = self::JIJI_OHANG[$branch];
            $branchYY = self::JIJI_YINYANG[$pillar[1]];

            // 천간 십성
            $stemSipsin = ($pi === 2) ? '일간' : self::getSipsin($dayElement, $dayYY, $stemEl, $stemYY);
            // 지지 십성 (본기 기준)
            $branchSipsin = self::getSipsin($dayElement, $dayYY, $branchEl, $branchYY);
            // 12운성
            $twelveStage = $this->getTwelveStage($this->dayPillar[0], $pillar[1]);

            // 지장간 십성
            $jjgSipsin = [];
            $labels = ['여기','중기','본기'];
            foreach (self::JIJANGGAN[$branch] as $idx => $item) {
                $jGan = $item[0]; $jRatio = $item[1];
                $jEl = self::CHEONGAN_OHANG[$jGan];
                $jYY = self::CHEONGAN_YINYANG[array_search($jGan, self::CHEONGAN)];
                $jSipsin = self::getSipsin($dayElement, $dayYY, $jEl, $jYY);
                $lbl = (count(self::JIJANGGAN[$branch])===2) ? ($idx===0?'여기':'본기') : ($labels[$idx]??'본기');
                $jjgSipsin[] = ['gan'=>$jGan,'element'=>$jEl,'sipsin'=>$jSipsin,'ratio'=>$jRatio,'label'=>$lbl];
                // 분포에 가중치 반영
                $distribution[$jSipsin] = ($distribution[$jSipsin] ?? 0) + $jRatio;
            }

            // 천간 분포 (일간 제외)
            if ($pi !== 2) $distribution[$stemSipsin] = ($distribution[$stemSipsin] ?? 0) + 1;

            $result[] = [
                'name' => $p['name'],
                'stem' => $stem,
                'branch' => $branch,
                'stem_sipsin' => $stemSipsin,
                'branch_sipsin' => $branchSipsin,
                'jijanggan_sipsin' => $jjgSipsin,
                'twelve_stage' => $twelveStage,
            ];
        }

        // 분포 반올림
        foreach ($distribution as $k => $v) $distribution[$k] = round($v, 2);
        arsort($distribution);

        $dominant = array_key_first($distribution);

        return [
            'pillars' => $result,
            'distribution' => $distribution,
            'dominant_sipsin' => $dominant,
            'dominant_sipsin_info' => self::SIPSIN_INFO[$dominant] ?? [],
        ];
    }

    /** 12운성 계산 */
    public function getTwelveStage($dayStemIndex, $branchIndex) {
        $start = self::TWELVE_STAGES_START[$dayStemIndex];
        $isYang = self::CHEONGAN_YINYANG[$dayStemIndex] === 0;
        $index = $isYang ? (($branchIndex - $start + 12) % 12) : (($start - $branchIndex + 12) % 12);
        return self::TWELVE_STAGES[$index];
    }

    // ============================================================
    // 신강/신약 + 용신/희신/기신/구신 (4신 분석)
    // ============================================================
    public function getDayMasterStrength() {
        $dayElement = $this->getDayMasterElement();
        $elements = $this->getAllElements();
        $support = 0; $oppose = 0;

        // 천간 세력 (일간 자신 제외)
        foreach ($elements['stems'] as $i => $stemIdx) {
            if ($i === 2) continue;
            $el = self::CHEONGAN_OHANG[self::CHEONGAN[$stemIdx]];
            $rel = self::getElementRelation($dayElement, $el);
            if ($rel==='same'||$rel==='generate_me') $support += 1.0;
            else $oppose += 1.0;
        }

        // 지지 세력 (지장간 비율 반영, 월지 1.5배 가중)
        foreach ($elements['branches'] as $i => $branchIdx) {
            $branch = self::JIJI[$branchIdx];
            foreach (self::JIJANGGAN[$branch] as $item) {
                $el = self::CHEONGAN_OHANG[$item[0]]; $ratio = $item[1];
                $weight = ($i === 1) ? $ratio * 1.5 : $ratio; // 월지=월령이므로 가중
                $rel = self::getElementRelation($dayElement, $el);
                if ($rel==='same'||$rel==='generate_me') $support += $weight;
                else $oppose += $weight;
            }
        }

        // 득령 보너스
        $monthBranch = self::JIJI[$this->monthPillar[1]];
        $monthMainElement = self::JIJI_OHANG[$monthBranch];
        $monthRel = self::getElementRelation($dayElement, $monthMainElement);
        $deukryeong = ($monthRel === 'same' || $monthRel === 'generate_me');
        if ($deukryeong) $support += 1.5;

        // 지지 뿌리(근) 확인 — 일간과 같은 오행 지장간이 있는 지지 수
        $roots = 0;
        foreach ($elements['branches'] as $branchIdx) {
            $branch = self::JIJI[$branchIdx];
            foreach (self::JIJANGGAN[$branch] as $item) {
                if (self::CHEONGAN_OHANG[$item[0]] === $dayElement) { $roots++; break; }
            }
        }

        $total = $support + $oppose;
        $ratio = ($total > 0) ? $support / $total : 0.5;

        // 신강/신약 판정
        if ($ratio >= 0.58) $strength = '매우 신강';
        elseif ($ratio >= 0.47) $strength = '신강';
        elseif ($ratio >= 0.40) $strength = '중화';
        elseif ($ratio >= 0.32) $strength = '신약';
        else $strength = '매우 신약';

        $isStrong = $ratio >= 0.47;

        // 4신(用神/喜神/忌神/仇神) 계산
        $fourGods = $this->calculateFourGods($ratio, $dayElement, $isStrong);

        return [
            'support' => round($support, 2),
            'oppose' => round($oppose, 2),
            'ratio' => round($ratio, 3),
            'is_strong' => $isStrong,
            'strength' => $strength,
            'deukryeong' => $deukryeong,
            'roots' => $roots,
            'yongshin' => $fourGods['yongshin'],
            'heeshin' => $fourGods['heeshin'],
            'gishin' => $fourGods['gishin'],
            'gushin' => $fourGods['gushin'],
            'four_gods_explanation' => $fourGods['explanation'],
            'description' => $this->getStrengthDescription($ratio, $dayElement, $deukryeong, $roots),
        ];
    }

    /**
     * 4신 계산 (용신·희신·기신·구신)
     * 
     * 신강 사주: 기운이 넘치므로 빼주는 것이 좋음
     *   용신 = 식상(설기) 또는 관성(극제) 
     *   희신 = 재성(설기의 결과물)
     *   기신 = 인성(더 강하게 만듦)
     *   구신 = 비겁(같은 기운 증가)
     * 
     * 신약 사주: 기운이 부족하므로 도와주는 것이 좋음
     *   용신 = 인성(나를 생) 또는 비겁(같은 힘)
     *   희신 = 비겁 또는 인성
     *   기신 = 관성(나를 극)
     *   구신 = 재성(인성을 극하므로)
     */
    private function calculateFourGods($ratio, $dayElement, $isStrong) {
        $cycle = self::OHANG;
        $idx = array_search($dayElement, $cycle);
        $bigyeop  = $dayElement;                  // 비겁: 같은 오행
        $siksang  = $cycle[($idx + 1) % 5];      // 식상: 내가 생하는 오행
        $jaesung  = $cycle[($idx + 2) % 5];      // 재성: 내가 극하는 오행
        $gwansung = $cycle[($idx + 3) % 5];      // 관성: 나를 극하는 오행
        $insung   = $cycle[($idx + 4) % 5];      // 인성: 나를 생하는 오행

        if ($isStrong) {
            return [
                'yongshin' => ['element'=>$siksang, 'type'=>'식상(食傷)'],
                'heeshin'  => ['element'=>$jaesung, 'type'=>'재성(財星)'],
                'gishin'   => ['element'=>$insung,  'type'=>'인성(印星)'],
                'gushin'   => ['element'=>$bigyeop, 'type'=>'비겁(比劫)'],
                'explanation' => sprintf(
                    "신강한 사주이므로 넘치는 기운을 빼주는 것이 핵심입니다.\n".
                    "• 용신(用神) = %s(%s) — 식상(食傷). 내가 가진 에너지를 밖으로 표현하고 발산시키는 오행입니다. 창의력과 표현력을 살리면 운이 좋아집니다.\n".
                    "• 희신(喜神) = %s(%s) — 재성(財星). 용신을 도와 재물과 실질적 성과를 만들어주는 오행입니다.\n".
                    "• 기신(忌神) = %s(%s) — 인성(印星). 이미 강한 나를 더 강하게 만들어 오히려 해가 되는 오행입니다.\n".
                    "• 구신(仇神) = %s(%s) — 비겁(比劫). 기신을 도와 나를 더욱 과하게 만드는 오행입니다.",
                    $siksang, self::OHANG_HANJA[array_search($siksang,$cycle)],
                    $jaesung, self::OHANG_HANJA[array_search($jaesung,$cycle)],
                    $insung,  self::OHANG_HANJA[array_search($insung,$cycle)],
                    $bigyeop, self::OHANG_HANJA[array_search($bigyeop,$cycle)]
                ),
            ];
        } else {
            return [
                'yongshin' => ['element'=>$insung,  'type'=>'인성(印星)'],
                'heeshin'  => ['element'=>$bigyeop, 'type'=>'비겁(比劫)'],
                'gishin'   => ['element'=>$gwansung, 'type'=>'관성(官星)'],
                'gushin'   => ['element'=>$jaesung,  'type'=>'재성(財星)'],
                'explanation' => sprintf(
                    "신약한 사주이므로 부족한 기운을 채워주는 것이 핵심입니다.\n".
                    "• 용신(用神) = %s(%s) — 인성(印星). 나를 낳아주고 키워주는 오행으로, 학문·자격증·정신력을 강화해줍니다.\n".
                    "• 희신(喜神) = %s(%s) — 비겁(比劫). 나와 같은 오행으로 동료·협력자의 힘을 빌려 함께 성장합니다.\n".
                    "• 기신(忌神) = %s(%s) — 관성(官星). 나를 극하는 오행으로, 직장·조직의 압박이 과해져 해가 됩니다.\n".
                    "• 구신(仇神) = %s(%s) — 재성(財星). 인성(용신)을 극하여 용신의 힘을 빼앗는 오행입니다.",
                    $insung,   self::OHANG_HANJA[array_search($insung,$cycle)],
                    $bigyeop,  self::OHANG_HANJA[array_search($bigyeop,$cycle)],
                    $gwansung, self::OHANG_HANJA[array_search($gwansung,$cycle)],
                    $jaesung,  self::OHANG_HANJA[array_search($jaesung,$cycle)]
                ),
            ];
        }
    }

    private function getStrengthDescription($ratio, $element, $deukryeong, $roots) {
        $ry = $deukryeong ? '월령(月令)의 기운을 얻어 득령(得令) 상태입니다.' : '월령의 기운을 얻지 못해 실령(失令) 상태입니다.';
        $rt = $roots > 0 ? "지지에 {$roots}개의 뿌리(根)를 두고 있어 " . ($roots>=2?'기반이 튼튼합니다.':'어느 정도 지지를 받고 있습니다.') : '지지에 뿌리가 없어 기반이 약합니다.';
        $desc = [
            '목'=>['강'=>'큰 나무처럼 강인한 의지와 추진력을 가졌습니다. 주변을 이끄는 리더십이 있으나, 고집이 세다는 평을 들을 수 있습니다. 기운을 식상(화)으로 발산하면 균형이 잡힙니다.',
                   '약'=>'새싹처럼 보살핌이 필요합니다. 수(水)의 도움으로 자양분을 얻고, 같은 목(木)의 지원으로 함께 성장하는 것이 중요합니다.'],
            '화'=>['강'=>'태양처럼 뜨거운 열정과 에너지를 발산합니다. 표현력이 뛰어나지만 감정 조절이 과제입니다. 토(土)로 설기하면 안정됩니다.',
                   '약'=>'촛불처럼 바람에 흔들리기 쉽습니다. 목(木)이 연료가 되어주고, 같은 화(火)의 온기를 모아야 합니다.'],
            '토'=>['강'=>'큰 산처럼 묵직하고 안정적인 중심을 잡습니다. 포용력이 크지만 변화에 둔할 수 있습니다. 금(金)으로 설기하면 활력을 되찾습니다.',
                   '약'=>'흙이 흩어지기 쉬운 상태입니다. 화(火)가 토를 굳혀주고, 같은 토의 뭉침으로 안정을 찾아야 합니다.'],
            '금'=>['강'=>'강철처럼 날카롭고 정확한 판단력을 지녔습니다. 결단력이 뛰어나지만 유연함을 길러야 합니다. 수(水)로 설기하면 부드러워집니다.',
                   '약'=>'금박처럼 외부 충격에 약합니다. 토(土)가 금을 보호해주고, 같은 금의 단단함이 필요합니다.'],
            '수'=>['강'=>'바다처럼 깊은 지혜와 포용력을 가졌습니다. 적응력이 뛰어나지만 방향을 잡는 것이 과제입니다. 목(木)으로 설기하면 목표가 생깁니다.',
                   '약'=>'이슬처럼 쉽게 증발합니다. 금(金)이 수를 생해주고, 같은 수의 흐름으로 힘을 모아야 합니다.'],
        ];
        $key = $ratio>=0.45?'강':'약';
        return ($desc[$element][$key]??'')."\n".$ry.' '.$rt;
    }

    // ============================================================
    // 합충형파해 관계 분석
    // ============================================================
    public function analyzeRelationships($externalBranches = null, $externalStems = null) {
        $pillarNames = $externalBranches ? ['대운/세운','년','월','일','시'] : ['년','월','일','시'];
        $pillars = $externalBranches
            ? array_merge([null], [$this->yearPillar, $this->monthPillar, $this->dayPillar, $this->hourPillar])
            : [$this->yearPillar, $this->monthPillar, $this->dayPillar, $this->hourPillar];

        $branches = []; $stems = [];
        if ($externalBranches) {
            $branches[] = $externalBranches; $stems[] = $externalStems;
        }
        foreach (($externalBranches ? array_slice($pillars,1) : $pillars) as $p) {
            $branches[] = ($p[1]!==null)?self::JIJI[$p[1]]:null;
            $stems[] = ($p[0]!==null)?self::CHEONGAN[$p[0]]:null;
        }

        $relations = [];
        $n = count($branches);

        // 육합
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$branches[$i]||!$branches[$j]) continue;
            foreach (self::YUKHAP as $ki => $pair) {
                if (self::matchPair($branches[$i],$branches[$j],$pair)) {
                    $el = self::YUKHAP_ELEMENT[$ki];
                    $relations[] = ['type'=>'육합','position'=>$pillarNames[$i].$pillarNames[$j],'chars'=>$branches[$i].$branches[$j],
                        'element'=>$el,'meaning'=>"{$branches[$i]}({$pair[0]})+{$branches[$j]}({$pair[1]}) 육합→{$el}\n두 지지가 서로 끌어당겨 하나의 새로운 기운({$el})을 만듭니다. 인간관계에서 인연이 깊고, 협력·결합의 긍정적 에너지입니다."];
                }
            }
        }
        // 삼합
        foreach (self::SAMHAP as $idx => $trio) {
            $found=[]; $pos=[];
            foreach ($branches as $bi => $b) { if ($b && in_array($b,$trio)) { $found[]=$b; $pos[]=$pillarNames[$bi]; } }
            if (count($found)>=2) {
                $el=self::SAMHAP_ELEMENT[$idx]; $full=(count($found)===3);
                $relations[] = ['type'=>'삼합','position'=>implode('',$pos),'chars'=>implode('',$found),'element'=>$el,
                    'meaning'=>implode('',$found).($full?' 완전삼합':' 반합(半合)')." → {$el}의 기운\n".($full?"세 지지가 모두 만나 {$el}의 기운이 극대화됩니다. 매우 강력한 합으로 사주 전체에 큰 영향을 줍니다.":"두 지지가 만나 부분적으로 {$el}의 기운이 형성됩니다. 나머지 하나가 대운/세운에서 올 때 완성됩니다.")];
            }
        }
        // 방합
        foreach (self::BANGHAP as $idx => $trio) {
            $found=[]; $pos=[];
            foreach ($branches as $bi => $b) { if ($b && in_array($b,$trio)) { $found[]=$b; $pos[]=$pillarNames[$bi]; } }
            if (count($found)>=3) {
                $el=self::BANGHAP_ELEMENT[$idx];
                $relations[] = ['type'=>'방합','position'=>implode('',$pos),'chars'=>implode('',$found),'element'=>$el,
                    'meaning'=>implode('',$found)." 방합 → {$el}국\n같은 방위의 세 지지가 모두 모여 {$el}의 기운이 최대로 강해집니다. 해당 오행이 사주를 지배하게 됩니다."];
            }
        }
        // 충
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$branches[$i]||!$branches[$j]) continue;
            foreach (self::CHUNG as $pair) {
                if (self::matchPair($branches[$i],$branches[$j],$pair)) {
                    $posD=['년'=>'조상/어린시절','월'=>'부모/사회생활','일'=>'자신/배우자','시'=>'자녀/노년','대운/세운'=>'시기적 운'];
                    $relations[] = ['type'=>'충','position'=>$pillarNames[$i].$pillarNames[$j],'chars'=>$branches[$i].$branches[$j],
                        'meaning'=>"{$branches[$i]}↔{$branches[$j]} 충(沖)\n서로 정반대 방향의 기운이 충돌합니다. ".($posD[$pillarNames[$i]]??'')."과(와) ".($posD[$pillarNames[$j]]??'')." 영역에서 변동·이동·갈등이 생길 수 있습니다. 하지만 충은 정체된 기운을 깨뜨려 새로운 변화를 만들기도 합니다."];
                }
            }
        }
        // 형
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$branches[$i]||!$branches[$j]) continue;
            foreach (self::HYUNG as $group) {
                if (count($group)>=2 && self::matchPair($branches[$i],$branches[$j],[$group[0],$group[1]])) {
                    $relations[] = ['type'=>'형','position'=>$pillarNames[$i].$pillarNames[$j],'chars'=>$branches[$i].$branches[$j],
                        'meaning'=>"{$branches[$i]}↔{$branches[$j]} 형(刑)\n서로 부딪히며 갈등과 시련을 만듭니다. '형'은 법적 문제, 건강 문제, 인간관계 마찰로 나타날 수 있지만, 이를 통해 성장하고 더 강해질 수 있습니다."];
                }
            }
        }
        // 해
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$branches[$i]||!$branches[$j]) continue;
            foreach (self::HAE_REL as $pair) {
                if (self::matchPair($branches[$i],$branches[$j],$pair)) {
                    $relations[] = ['type'=>'해','position'=>$pillarNames[$i].$pillarNames[$j],'chars'=>$branches[$i].$branches[$j],
                        'meaning'=>"{$branches[$i]}↔{$branches[$j]} 해(害)\n겉으로는 드러나지 않지만 속으로 손해를 끼치는 관계입니다. 뒤에서 방해하거나 배신을 당할 수 있으니, 대인관계에서 신중함이 필요합니다."];
                }
            }
        }
        // 파
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$branches[$i]||!$branches[$j]) continue;
            foreach (self::PA as $pair) {
                if (self::matchPair($branches[$i],$branches[$j],$pair)) {
                    $relations[] = ['type'=>'파','position'=>$pillarNames[$i].$pillarNames[$j],'chars'=>$branches[$i].$branches[$j],
                        'meaning'=>"{$branches[$i]}↔{$branches[$j]} 파(破)\n진행 중인 일에 예상치 못한 변수가 생길 수 있습니다. 계획이 틀어지거나 약속이 깨지는 경우가 있으니 항상 플랜B를 준비하세요."];
                }
            }
        }
        // 천간합
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$stems[$i]||!$stems[$j]) continue;
            foreach (self::CHEONGAN_HAP as $ki => $pair) {
                if (self::matchPair($stems[$i],$stems[$j],$pair)) {
                    $el=self::CHEONGAN_HAP_ELEMENT[$ki];
                    $relations[] = ['type'=>'천간합','position'=>$pillarNames[$i].'간'.$pillarNames[$j].'간','chars'=>$stems[$i].$stems[$j],
                        'element'=>$el,'meaning'=>"{$stems[$i]}+{$stems[$j]} 천간합→{$el}\n두 천간이 합하여 {$el}의 새로운 기운을 만듭니다. 사교적이고 타인과 잘 어울리는 성향을 나타냅니다."];
                }
            }
        }
        // 천간충
        for ($i=0;$i<$n;$i++) for ($j=$i+1;$j<$n;$j++) {
            if (!$stems[$i]||!$stems[$j]) continue;
            foreach (self::CHEONGAN_CHUNG as $pair) {
                if (self::matchPair($stems[$i],$stems[$j],$pair)) {
                    $relations[] = ['type'=>'천간충','position'=>$pillarNames[$i].'간'.$pillarNames[$j].'간','chars'=>$stems[$i].$stems[$j],
                        'meaning'=>"{$stems[$i]}↔{$stems[$j]} 천간충\n같은 오행끼리 양과 음이 충돌합니다. 외부적으로 드러나는 갈등과 변화가 있으며, 의지와 환경 사이의 마찰이 발생할 수 있습니다."];
                }
            }
        }
        return $relations;
    }

    public static function matchPair($a,$b,$pair) { return ($a===$pair[0]&&$b===$pair[1])||($a===$pair[1]&&$b===$pair[0]); }

    // ============================================================
    // 공망 계산
    // ============================================================
    public function getGongmang() {
        $stemIdx=$this->dayPillar[0]; $branchIdx=$this->dayPillar[1];
        $dayIn60=0;
        for ($i=0;$i<60;$i++) { if ($i%10===$stemIdx && $i%12===$branchIdx) { $dayIn60=$i; break; } }
        $xunStart=(int)floor($dayIn60/10)*10;
        $usedBranches=[];
        for ($i=0;$i<10;$i++) $usedBranches[]=($xunStart+$i)%12;
        $missing=[];
        for ($b=0;$b<12;$b++) { if (!in_array($b,$usedBranches)) $missing[]=self::JIJI[$b]; }
        return $missing;
    }

    // ============================================================
    // 오행 관계 유틸리티
    // ============================================================
    public static function getElementRelation($base, $target) {
        $cycle=self::OHANG; $bi=array_search($base,$cycle); $ti=array_search($target,$cycle);
        $diff=($ti-$bi+5)%5;
        return ['same','i_generate','i_control','control_me','generate_me'][$diff] ?? 'same';
    }

    // ============================================================
    // 결과 출력
    // ============================================================
    public function getResult() {
        $sipsinFull = $this->analyzeSipsinFull();
        $strength = $this->getDayMasterStrength();
        return [
            'input' => ['year'=>$this->birthYear,'month'=>$this->birthMonth,'day'=>$this->birthDay,'hour'=>$this->birthHour,'gender'=>$this->gender,'calendar_type'=>$this->calendarType],
            'solar_date' => $this->solarDate,
            'year_pillar' => $this->getPillarInfo($this->yearPillar),
            'month_pillar' => $this->getPillarInfo($this->monthPillar),
            'day_pillar' => $this->getPillarInfo($this->dayPillar),
            'hour_pillar' => $this->getPillarInfo($this->hourPillar),
            'zodiac' => self::ZODIAC_ANIMALS[self::JIJI[$this->yearPillar[1]]],
            'day_master' => self::CHEONGAN[$this->dayPillar[0]],
            'day_master_element' => self::CHEONGAN_OHANG[self::CHEONGAN[$this->dayPillar[0]]],
            'siji_name' => ($this->birthHour!==null)?self::SIJI_NAMES[self::HOUR_TO_JIJI[$this->birthHour]]:'시간 미상',
            'jijanggan' => $this->getJijangganInfo(),
            'gongmang' => $this->getGongmang(),
            'relationships' => $this->analyzeRelationships(),
            'day_master_strength' => $strength,
            'sipsin_full' => $sipsinFull,
        ];
    }

    private function getJijangganInfo() {
        $pillars=['year'=>$this->yearPillar,'month'=>$this->monthPillar,'day'=>$this->dayPillar,'hour'=>$this->hourPillar];
        $labels=['여기','중기','본기']; $info=[];
        foreach ($pillars as $key=>$p) {
            if ($p[1]===null) { $info[$key]=[]; continue; }
            $branch=self::JIJI[$p[1]]; $jjg=self::JIJANGGAN[$branch]; $details=[];
            foreach ($jjg as $idx=>$item) {
                $gan=$item[0]; $ratio=$item[1]; $element=self::CHEONGAN_OHANG[$gan];
                $lbl=(count($jjg)===2)?($idx===0?'여기':'본기'):($labels[$idx]??'본기');
                $details[]=['gan'=>$gan,'hanja'=>self::CHEONGAN_HANJA[array_search($gan,self::CHEONGAN)],'element'=>$element,'element_hanja'=>self::OHANG_HANJA[array_search($element,self::OHANG)],'ratio'=>$ratio,'label'=>$lbl];
            }
            $info[$key]=$details;
        }
        return $info;
    }

    private function getPillarInfo($pillar) {
        if ($pillar[0]===null||$pillar[1]===null) return ['stem'=>'?','branch'=>'?','stem_hanja'=>'?','branch_hanja'=>'?','stem_element'=>'토','branch_element'=>'토','stem_element_hanja'=>'土','branch_element_hanja'=>'土','stem_yinyang'=>'양','branch_yinyang'=>'양','stem_color'=>'#FF9800','branch_color'=>'#FF9800','text'=>'??','hanja'=>'??','stem_index'=>0,'branch_index'=>0];
        $stem=self::CHEONGAN[$pillar[0]]; $branch=self::JIJI[$pillar[1]];
        $stemH=self::CHEONGAN_HANJA[$pillar[0]]; $branchH=self::JIJI_HANJA[$pillar[1]];
        $stemEl=self::CHEONGAN_OHANG[$stem]; $branchEl=self::JIJI_OHANG[$branch];
        $sYY=self::CHEONGAN_YINYANG[$pillar[0]]===0?'양':'음'; $bYY=self::JIJI_YINYANG[$pillar[1]]===0?'양':'음';
        $sci=array_search($stemEl,self::OHANG); $bci=array_search($branchEl,self::OHANG);
        return ['stem'=>$stem,'branch'=>$branch,'stem_hanja'=>$stemH,'branch_hanja'=>$branchH,'stem_element'=>$stemEl,'branch_element'=>$branchEl,'stem_element_hanja'=>self::OHANG_HANJA[$sci],'branch_element_hanja'=>self::OHANG_HANJA[$bci],'stem_yinyang'=>$sYY,'branch_yinyang'=>$bYY,'stem_color'=>self::OHANG_COLORS[$sci],'branch_color'=>self::OHANG_COLORS[$bci],'text'=>$stem.$branch,'hanja'=>$stemH.$branchH,'stem_index'=>$pillar[0],'branch_index'=>$pillar[1]];
    }

    // ============================================================
    // 공개 접근자
    // ============================================================
    public function getSajuString() { $r=$this->getResult(); return "{$r['year_pillar']['text']} {$r['month_pillar']['text']} {$r['day_pillar']['text']} {$r['hour_pillar']['text']}"; }
    public function getDayMaster() { return self::CHEONGAN[$this->dayPillar[0]]; }
    public function getDayMasterElement() { return self::CHEONGAN_OHANG[$this->getDayMaster()]; }
    public function getYearPillar() { return $this->yearPillar; }
    public function getMonthPillar() { return $this->monthPillar; }
    public function getDayPillar() { return $this->dayPillar; }
    public function getHourPillar() { return $this->hourPillar; }
    public function getGender() { return $this->gender; }
    public function getBirthHour() { return $this->birthHour; }
    public function getAllElements() {
        return ['stems'=>[$this->yearPillar[0],$this->monthPillar[0],$this->dayPillar[0],$this->hourPillar[0]??$this->dayPillar[0]],'branches'=>[$this->yearPillar[1],$this->monthPillar[1],$this->dayPillar[1],$this->hourPillar[1]??$this->dayPillar[1]]];
    }
}
