# 사주포춘

PHP와 MySQL 기반으로 구현된 사주·운세 분석 웹 서비스입니다. 사용자는 생년월일, 출생 시간, 성별, 양력/음력 정보를 입력해 사주 분석 결과를 확인할 수 있고, 회원별 분석 이력과 티켓 기반 프리미엄 분석 기능을 제공합니다. 관리자는 회원, 티켓, 분석 기록, 보고서 생성/공유 기능을 관리할 수 있습니다.

> 본 프로젝트의 사주·운세·심리 해석은 엔터테인먼트 및 참고용 콘텐츠입니다. 의료, 법률, 투자, 진로 등 중요한 의사결정의 근거로 사용하지 않도록 안내 문구를 추가하는 것을 권장합니다.

## 주요 기능

### 사용자 기능

* 회원가입, 로그인, 로그아웃
* CSRF 토큰 기반 폼 보호
* 비밀번호 해시 저장
* 회원가입 시 기본 티켓 지급
* 다중 사주 프로필 등록, 수정, 삭제, 기본 프로필 설정
* 사주 분석 이력 저장 및 조회
* 티켓 사용 내역 조회
* 모바일 하단 네비게이션 중심의 반응형 UI

### 사주/운세 기능

* 기본 사주 분석
* 오행 분석
* 십신 분석
* 격국 분석
* 대운 분석
* 세운 분석
* 종합 운세 분석
* 신년운세
* 토정비결형 연간 운세
* 오늘/내일/지정일 운세
* 재물운, 애정운, 직업운, 건강운 집중 풀이
* 짝궁합 분석
* 정통사주 리포트
* 간단 심리풀이
* 관상 페이지 UI

### 관리자 기능

* 관리자 대시보드
* 회원 목록 및 상세 조회
* 회원 권한 변경
* 개별 회원 티켓 지급/차감
* 전체 회원 티켓 일괄 지급
* 티켓 로그 조회
* 분석 기록 조회
* 관리자용 보고서 생성
* 공유 링크 기반 보고서 열람
* 이메일 발송 함수 기반 보고서 링크 전송

## 기술 스택

* Backend: PHP
* Database: MySQL 또는 MariaDB
* DB Access: PDO
* Frontend: HTML, CSS, JavaScript
* UI Assets: Font Awesome, Google Fonts, Chart.js CDN
* 권장 실행 환경: XAMPP, APM, 또는 PHP + MySQL 서버

## 권장 요구사항

* PHP 7.4 이상 권장
* MySQL 5.7 이상 또는 MariaDB 10.2 이상 권장
* PHP 확장

  * `pdo_mysql`
  * `mbstring`
  * `json`
  * `openssl`
* Apache 또는 Nginx
* 로컬 개발 시 XAMPP 권장

## 프로젝트 구조

```text
saju/
├── index.php                     # 메인 라우터
├── install.php                   # DB 설치 스크립트
├── install.lock                  # 설치 완료 잠금 파일
├── migrate_profiles.php          # 프로필 테이블 마이그레이션 보조 스크립트
├── config/
│   ├── config.php                # 사이트 전역 설정
│   └── database.php              # DB 연결 설정
├── auth/
│   ├── login.php                 # 로그인
│   ├── register.php              # 회원가입
│   ├── forgot_password.php       # 비밀번호 찾기 화면
│   └── logout.php                # 로그아웃
├── includes/
│   ├── auth_check.php            # 로그인 체크
│   ├── admin_check.php           # 관리자 권한 체크
│   ├── functions.php             # 공통 함수
│   ├── fortune_services.php      # 운세 페이지 공통 생성 함수
│   ├── report_share_functions.php# 보고서 공유/메일 함수
│   ├── header.php                # 사용자 공통 헤더
│   └── footer.php                # 사용자 공통 푸터
├── pages/
│   ├── home.php                  # 홈/대시보드
│   ├── analyze.php               # 사주 분석 입력/처리
│   ├── result.php                # 분석 결과
│   ├── history.php               # 분석 이력
│   ├── mypage.php                # 마이페이지/프로필 관리
│   ├── api_profiles.php          # 프로필 AJAX API
│   ├── premium.php               # 프리미엄 분석 목록
│   ├── ticket_history.php        # 티켓 내역
│   ├── yearly_fortune.php        # 신년운세/토정비결
│   ├── date_fortune.php          # 오늘/내일/지정일 운세
│   ├── focus_fortune.php         # 재물/애정/직업/건강운
│   ├── daeun_fortune.php         # 대운풀이
│   ├── traditional_saju.php      # 정통사주
│   ├── compatibility.php         # 짝궁합
│   ├── psychology.php            # 심리풀이
│   ├── physiognomy.php           # 관상
│   └── shared_report.php         # 공유 보고서 조회
├── admin/
│   ├── index.php                 # 관리자 대시보드
│   ├── users.php                 # 회원 관리
│   ├── tickets.php               # 티켓 관리
│   ├── history.php               # 분석 기록 관리
│   └── report.php                # 관리자 보고서 생성
├── saju/
│   ├── SajuEngine.php            # 사주 계산 엔진
│   ├── OhangAnalysis.php         # 오행 분석
│   ├── FortuneInterpreter.php    # 십신/격국/대운/세운/종합 분석
│   ├── DaeunAnalyzer.php         # 대운 분석기
│   ├── DaeunCombinationEngine.php# 대운 조합 해석 엔진
│   ├── PatternDetector.php       # 패턴 감지
│   ├── SajuStoryGenerator.php    # 스토리형 해석 생성
│   ├── interpretation_data/      # 해석 데이터
│   ├── daeun_data/               # 대운 해석 데이터
│   └── combination_data/         # 조합 해석 데이터
└── assets/
    ├── css/style.css             # 전체 스타일
    └── js/app.js                 # 공통 JS
```

## 설치 방법

### 1. 프로젝트 배치

ZIP 압축을 해제한 뒤 `saju` 폴더를 XAMPP의 `htdocs` 아래에 복사합니다.

```text
xampp/htdocs/saju
```

기본 설정은 `/saju` 경로에 맞춰져 있습니다.

```php
// config/config.php
define('SITE_URL', '/saju');
```

다른 경로에 배포할 경우 `SITE_URL`과 일부 리다이렉트 경로를 함께 확인해야 합니다.

### 2. 데이터베이스 설정 확인

기본 DB 설정은 다음과 같습니다.

```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'saju_db');
define('DB_CHARSET', 'utf8mb4');
```

XAMPP 기본 환경이라면 별도 수정 없이 사용할 수 있습니다. 운영 서버에서는 반드시 DB 사용자명, 비밀번호, DB명을 실제 환경에 맞게 변경하세요.

### 3. 설치 잠금 파일 확인

프로젝트에 `install.lock` 파일이 포함되어 있을 수 있습니다. 신규 환경에서 DB가 아직 설치되지 않았다면 `install.lock` 파일을 삭제한 뒤 설치 페이지에 접속하는 것을 권장합니다.

```text
saju/install.lock
```

### 4. 설치 페이지 실행

브라우저에서 아래 주소로 접속합니다.

```text
http://localhost/saju/install.php
```

설치 버튼을 누르면 다음 작업이 수행됩니다.

* `saju_db` 데이터베이스 생성
* 기본 테이블 생성
* 관리자 계정 생성
* 테스트 사용자 계정 생성
* 초기 티켓 로그 생성
* `install.lock` 생성

### 5. 접속

설치 후 아래 주소로 접속합니다.

```text
http://localhost/saju
```

설치가 완료된 경우 로그인 화면 또는 홈 화면으로 이동합니다.

## 기본 계정

설치 스크립트는 테스트용 계정을 생성합니다.

| 구분     | 이메일              | 비밀번호        |
| ------ | ---------------- | ----------- |
| 관리자    | `admin@saju.com` | `admin1234` |
| 일반 사용자 | `user@saju.com`  | `user1234`  |

운영 환경에서는 기본 계정을 즉시 삭제하거나 비밀번호를 변경해야 합니다.

## 주요 URL

| 기능       | URL                       |
| -------- | ------------------------- |
| 메인       | `/saju/`                  |
| 설치       | `/saju/install.php`       |
| 로그인      | `/saju/auth/login.php`    |
| 회원가입     | `/saju/auth/register.php` |
| 홈        | `/saju/pages/home.php`    |
| 사주 분석    | `/saju/pages/analyze.php` |
| 프리미엄 분석  | `/saju/pages/premium.php` |
| 마이페이지    | `/saju/pages/mypage.php`  |
| 관리자 대시보드 | `/saju/admin/index.php`   |
| 회원 관리    | `/saju/admin/users.php`   |
| 티켓 관리    | `/saju/admin/tickets.php` |
| 보고서 생성   | `/saju/admin/report.php`  |

## 데이터베이스 테이블

설치 스크립트 기준으로 생성되는 주요 테이블은 다음과 같습니다.

| 테이블                    | 설명                                     |
| ---------------------- | -------------------------------------- |
| `saju_users`           | 회원 정보, 권한, 보유 티켓                       |
| `saju_fortune_history` | 사주 분석 입력값 및 분석 결과 JSON 저장              |
| `saju_ticket_logs`     | 티켓 지급/사용 로그                            |
| `saju_profiles`        | 회원별 다중 사주 프로필                          |
| `saju_shared_reports`  | 관리자 보고서 공유 링크 저장. 보고서 공유 기능 사용 시 자동 생성 |

## 티켓 정책

기본 설정은 `config/config.php`에서 관리합니다.

```php
define('DEFAULT_TICKETS', 3);

define('FREE_FEATURES', ['basic_saju', 'ohang']);

define('PREMIUM_FEATURES', [
    'sipsin' => ['name' => '십신 분석', 'tickets' => 1],
    'gyeokguk' => ['name' => '격국 분석', 'tickets' => 1],
    'daeun' => ['name' => '대운 분석', 'tickets' => 1],
    'seun' => ['name' => '세운 분석', 'tickets' => 1],
    'love' => ['name' => '연애운 분석', 'tickets' => 1],
    'career' => ['name' => '직업운 분석', 'tickets' => 1],
    'wealth' => ['name' => '재물운 분석', 'tickets' => 1],
    'comprehensive' => ['name' => '인생 종합 분석', 'tickets' => 2],
]);
```

프리미엄 분석 실행 시 보유 티켓이 부족하면 분석이 제한됩니다. 티켓 지급과 차감은 관리자 화면에서 처리할 수 있습니다.

## 환경 설정 포인트

### 사이트명 및 버전

```php
// config/config.php
define('SITE_NAME', '사주포춘');
define('SITE_VERSION', '1.0.0');
```

### 타임존

```php
date_default_timezone_set('Asia/Seoul');
```

### 에러 표시

현재 개발 편의를 위해 에러 표시가 활성화되어 있습니다.

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

운영 환경에서는 다음처럼 비활성화하는 것을 권장합니다.

```php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
```

## 개발 참고사항

* Composer 의존성은 확인되지 않습니다.
* CSS와 JavaScript는 `assets/` 아래에서 직접 관리합니다.
* 차트는 Chart.js CDN을 사용합니다.
* 아이콘은 Font Awesome CDN을 사용합니다.
* 폰트는 Google Fonts의 Noto Sans KR을 사용합니다.
* 분석 결과는 `saju_fortune_history` 테이블에 JSON 형태로 저장됩니다.
* 프로필 관리 API는 `pages/api_profiles.php`에서 AJAX 방식으로 처리합니다.
* 보고서 공유 기능은 `saju_shared_reports` 테이블을 런타임에 생성할 수 있습니다.

## 운영 전 체크리스트

운영 배포 전에는 아래 항목을 반드시 확인하세요.

* `install.php` 삭제 또는 접근 차단
* `install.lock` 유지
* 기본 관리자/사용자 계정 삭제 또는 비밀번호 변경
* `config/database.php`의 DB 계정 변경
* `display_errors` 비활성화
* HTTPS 적용
* 세션 쿠키 보안 옵션 적용
* 관리자 페이지 접근 제한 강화
* DB 백업 정책 수립
* PHP `mail()` 사용 시 실제 SMTP/메일 발송 환경 검증
* `.bak`, `test_phase*.php`, `test_profiles.php`, `migrate_profiles.php` 등 개발/백업 파일 제거 또는 접근 차단
* `__MACOSX` 폴더와 `._` 메타 파일 제거
* 개인정보 처리방침, 이용약관, 환불/티켓 정책, 운세 콘텐츠 면책 문구 추가

## 주의사항

### 음력 변환 정확도

`SajuEngine.php`에는 음력 입력을 양력으로 변환하는 로직이 포함되어 있으나, 실제 만세력 수준의 정밀 변환이 아니라 추정 계산 방식입니다. 정확한 음력/윤달 처리가 필요한 서비스라면 공신력 있는 만세력 데이터 또는 검증된 음양력 변환 라이브러리로 교체하는 것을 권장합니다.

### 보안

현재 프로젝트는 로컬 개발과 프로토타입 운영에 가까운 구조입니다. 운영 서비스로 공개하기 전에는 관리자 인증, 설치 파일 차단, 기본 계정 제거, 세션 보안, 입력값 검증, 접근 제어, 로그 관리 등을 강화해야 합니다.

### 콘텐츠 책임

사주, 운세, 관상, 심리풀이 콘텐츠는 참고용으로 제공되어야 합니다. 사용자에게 결과가 확정적 사실이 아니라는 점을 명확히 안내하는 것이 좋습니다.

## 라이선스

별도 라이선스 파일은 포함되어 있지 않습니다. 외부에 공개하거나 상업적으로 배포할 경우 프로젝트 소유자 기준의 라이선스를 명시하세요.
