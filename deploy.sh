#!/bin/bash
# ============================================
# SICA - Script de Despliegue al Host
# Uso: ./deploy.sh [archivo|directorio|--all]
# ============================================

HOST="u557645733@193.42.137.76"
PORT="65002"
REMOTE_BASE="/home/u557645733/domains/micasasica.com/public_html"
LOCAL_BASE="public_html"

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

deploy_file() {
    local file="$1"
    local remote="${REMOTE_BASE}/${file#$LOCAL_BASE/}"
    echo -e "${YELLOW}Subiendo:${NC} $file → $remote"
    scp -P "$PORT" "$file" "$HOST:$remote" && echo -e "${GREEN}✅ OK${NC}" || echo "❌ ERROR"
}

deploy_all() {
    echo -e "${YELLOW}🚀 Desplegando todo el proyecto...${NC}"
    # Excluir data/, uploads/, .git/
    rsync -avz --delete \
        --exclude 'data/' \
        --exclude 'admin/uploads/' \
        --exclude '.git/' \
        --exclude '.deepcode/' \
        -e "ssh -p $PORT" \
        "$LOCAL_BASE/" "$HOST:$REMOTE_BASE/"
    echo -e "${GREEN}✅ Despliegue completo${NC}"
}

deploy_admin() {
    echo -e "${YELLOW}📊 Desplegando panel admin...${NC}"
    for f in "$LOCAL_BASE"/admin/*.php "$LOCAL_BASE"/admin/includes/*.php "$LOCAL_BASE"/admin/api/*.php; do
        [ -f "$f" ] && deploy_file "$f"
    done
    deploy_file "$LOCAL_BASE/admin/assets/css/admin.css"
    deploy_file "$LOCAL_BASE/admin/assets/js/admin.js"
    echo -e "${GREEN}✅ Admin desplegado${NC}"
}

deploy_sidebar() {
    deploy_file "$LOCAL_BASE/admin/includes/sidebar.php"
    deploy_file "$LOCAL_BASE/admin/includes/sidebar-tareas.php"
}

deploy_css() {
    deploy_file "$LOCAL_BASE/admin/assets/css/admin.css"
    deploy_file "$LOCAL_BASE/assets/css/style.css"
    echo -e "${YELLOW}⚠️  Recuerda actualizar ?v=X en los HTML para burlar CDN${NC}"
}

case "${1:-}" in
    --all)
        deploy_all
        ;;
    --admin)
        deploy_admin
        ;;
    --sidebar)
        deploy_sidebar
        ;;
    --css)
        deploy_css
        ;;
    "")
        echo "Uso: ./deploy.sh [archivo|--all|--admin|--sidebar|--css]"
        echo ""
        echo "  archivo.php    Subir un archivo específico"
        echo "  --all          Desplegar todo el proyecto"
        echo "  --admin        Desplegar solo el panel admin"
        echo "  --sidebar      Desplegar solo los sidebars"
        echo "  --css          Desplegar solo archivos CSS"
        ;;
    *)
        if [ -f "$1" ]; then
            deploy_file "$1"
        elif [ -f "$LOCAL_BASE/$1" ]; then
            deploy_file "$LOCAL_BASE/$1"
        else
            echo "❌ Archivo no encontrado: $1"
        fi
        ;;
esac
