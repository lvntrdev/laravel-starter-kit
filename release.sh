#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
GRAY='\033[0;90m'
BOLD='\033[1m'
NC='\033[0m'

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

info()   { echo -e "  ${GREEN}INFO${NC}    ${1}"; }
error()  { echo -e "  ${RED}ERROR${NC}   ${1}"; exit 1; }
warn()   { echo -e "  ${YELLOW}WARN${NC}    ${1}"; }
detail() { printf "  %-32s %b\n" "${1}" "${2}"; }

[[ ! -d "${DIR}/.git" ]] && error "Bu script yalnızca paket kaynak dizininden çalıştırılabilir."

git -C "${DIR}" remote | grep -q . || error "Git remote yapılandırılmamış.\n  Çalıştır: git remote add origin <repo-url>"

if [[ -n "$(git -C "${DIR}" status --porcelain)" ]]; then
    echo -e "\n  ${RED}ERROR${NC}   Commit edilmemiş değişiklikler var:\n"
    git -C "${DIR}" status --short | sed 's/^/    /'
    echo -e "\n  Önce değişiklikleri commit et, sonra release yap.\n"
    exit 1
fi

echo ""
echo -e "  ${BOLD}Starter Kit Yayın${NC}"
echo ""

CURRENT_TAG=$(git -C "${DIR}" describe --tags --abbrev=0 2>/dev/null || echo "")
if [[ -n "${CURRENT_TAG}" ]]; then
    detail "Mevcut versiyon" "${CYAN}${CURRENT_TAG}${NC}"
else
    detail "Mevcut versiyon" "${YELLOW}henüz etiket yok${NC}"
fi

parse_version() {
    local ver="${1:-0.0.0}"
    ver="${ver#v}"
    echo "$(echo "${ver}" | cut -d. -f1) $(echo "${ver}" | cut -d. -f2) $(echo "${ver}" | cut -d. -f3)"
}

bump_version() {
    local type="${1}"
    local major minor patch
    read -r major minor patch <<< "$(parse_version "${CURRENT_TAG:-0.0.0}")"
    major="${major:-0}"; minor="${minor:-0}"; patch="${patch:-0}"
    case "${type}" in
        major) echo "$((major + 1)).0.0" ;;
        minor) echo "${major}.$((minor + 1)).0" ;;
        patch) echo "${major}.${minor}.$((patch + 1))" ;;
    esac
}

PATCH_VER=$(bump_version patch)
MINOR_VER=$(bump_version minor)
MAJOR_VER=$(bump_version major)

echo ""
echo "  Versiyon artırma tipi:"
echo "  1) Patch (hata düzeltme)     → ${PATCH_VER}"
echo "  2) Minor (yeni özellik)      → ${MINOR_VER}"
echo "  3) Major (kıran değişiklik)  → ${MAJOR_VER}"
echo "  4) Özel versiyon"
echo ""
read -rp "  Seçim [1-4, varsayılan 1]: " CHOICE
CHOICE="${CHOICE:-1}"

case "${CHOICE}" in
    1) VERSION="${PATCH_VER}" ;;
    2) VERSION="${MINOR_VER}" ;;
    3) VERSION="${MAJOR_VER}" ;;
    4)
        read -rp "  Versiyon (örn. 1.0.0): " VERSION
        [[ -z "${VERSION}" ]] && error "Versiyon boş olamaz."
        ;;
    *) error "Geçersiz seçim." ;;
esac

[[ "${VERSION}" != v* ]] && VERSION="v${VERSION}"
CLEAN_VERSION="${VERSION#v}"

if git -C "${DIR}" tag -l "${VERSION}" | grep -q "^${VERSION}$"; then
    error "${VERSION} etiketi zaten mevcut."
fi

REMOTE=$(git -C "${DIR}" remote get-url origin 2>/dev/null || echo "bilinmiyor")
CURRENT_BRANCH=$(git -C "${DIR}" rev-parse --abbrev-ref HEAD)

echo ""
detail "Yeni versiyon" "${GREEN}${VERSION}${NC}"
detail "Remote"        "${REMOTE}"
detail "Branch"        "${CURRENT_BRANCH}"
echo ""

read -rp "  ${VERSION} yayınlansın mı? [E/h]: " CONFIRM
CONFIRM="${CONFIRM:-E}"
[[ "${CONFIRM}" =~ ^[EeYy]$ ]] || { warn "Yayın iptal edildi."; exit 0; }

extract_changelog() {
    local version="${1}"
    local changelog="${DIR}/CHANGELOG.md"
    [[ ! -f "${changelog}" ]] && return

    local escaped="${version//./\\.}"
    awk "/^## \[?${escaped}\]?/{found=1; next} found && /^## /{exit} found && /^---[[:space:]]*$/{next} found{print}" "${changelog}" \
        | sed -e 's/[[:space:]]*$//' \
        | sed -e '/./,$!d' \
        | awk 'NF{last=NR} {line[NR]=$0} END{for(i=1;i<=last;i++) print line[i]}'
}

CHANGELOG_BODY=$(extract_changelog "${CLEAN_VERSION}")

if [[ -z "${CHANGELOG_BODY}" ]]; then
    echo ""
    warn "CHANGELOG.md içinde ${CLEAN_VERSION} girişi bulunamadı."
    read -rp "  CHANGELOG olmadan ${VERSION} etiketi oluşturulsun mu? [e/H]: " CL_CONFIRM
    CL_CONFIRM="${CL_CONFIRM:-H}"
    [[ "${CL_CONFIRM}" =~ ^[EeYy]$ ]] || { warn "Yayın iptal edildi."; exit 0; }
    git -C "${DIR}" tag -a "${VERSION}" -m "Release ${VERSION}"
    detail "${VERSION} etiketi" "${YELLOW}OLUŞTURULDU${NC} ${GRAY}(CHANGELOG'suz)${NC}"
else
    git -C "${DIR}" tag -a "${VERSION}" -m "Release ${VERSION}" -m "${CHANGELOG_BODY}"
    detail "${VERSION} etiketi" "${GREEN}OLUŞTURULDU${NC} ${GRAY}(CHANGELOG'dan dolduruldu)${NC}"
fi

echo -e "  ${GRAY}→${NC} Remote'a gönderiliyor (${CURRENT_BRANCH})..."
git -C "${DIR}" push origin "${CURRENT_BRANCH}" --tags
detail "Push" "${GREEN}TAMAM${NC}"

echo ""
echo -e "  ${GREEN}${VERSION} başarıyla yayınlandı!${NC}"
echo ""
echo "  Paketi yüklemek için:"
echo -e "  ${CYAN}composer require lvntr/laravel-starter-kit:\"^${CLEAN_VERSION}\"${NC}"
echo ""
